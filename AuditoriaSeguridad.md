# Auditoría de seguridad — API de Items (Programación 4)

**Fecha:** 31/08/2026
**Alcance:** Código fuente del proyecto (`public/index.php`, `src/*`, `https-proxy.php`).
**Vectores analizados:** XSS, CSRF, CORS y SQL Injection.

Uso de esta auditoría: documento **didáctico** para una clase. Cada sección tiene
(i) qué se revisó, (ii) estado actual, (iii) recomendación y (iv) pasos para
aplicarla. El código es un ejemplo docente, no una API lista para producción.

---

## 1. Resumen ejecutivo

| Vector | Riesgo actual | Severidad | Estado |
|---|---|---|---|
| SQL Injection | No vulnerable | Baja | ✅ Mitigado con sentencias preparadas (PDO) |
| XSS | Bajo (API pura JSON) | Baja/Media | ⚠️ Reforzar con cabeceras de seguridad |
| CSRF | Bajo (API con JWT en `Authorization`) | Baja/Media | ⚠️ Requiere mitigación si se suma frontend web |
| CORS | Sin política definida | Media | ⚠️ Añadir `Access-Control-Allow-Origin` explícito |

**Hallazgos clave de contexto (no son brechas en sí, pero conviene corregirlos):**

- **Secreto JWT por defecto fijo** en `public/index.php:75,130` (`'secreto-solo-para-desarrollo-cambiar'`). Si se despliega sin `JWT_SECRET`, cualquiera puede **forjar tokens** y autenticarse (riesgo **Crítico** en producción, aceptable solo en demo).
- **El bloque "exigir HTTPS" es código muerto** (ver §3): la comprobación `defined('INTERNAL_SECRET')` se evalúa *antes* de que la constante se defina, por lo que nunca se ejecuta; además el proxy no inyecta el secreto esperado. El resultado real es que **HTTP plano también funciona** (el proxy TLS pasó las pruebas sirviendo tanto por HTTPS como por HTTP directo al worker).

---

## 2. SQL Injection

### Estado actual: ✅ No vulnerable

**Análisis:** Todas las consultas usan **sentencias preparadas** de PDO con marcadores de parámetros; el servidor envía valores y estructura SQL por separado.

`src/Repositories/SqliteItemRepository.php` y `SqliteUserRepository.php`:

```php
$stmt = $this->pdo->prepare('SELECT ... WHERE id = :id');
$stmt->execute([':id' => $id]);
```

Ningún valor del usuario se concatena dentro de una cadena SQL. La concatenación
que existe solo arma el **DSN** de la conexión (`'sqlite:' . $dbFile`), y `$dbFile`
es fijo y controlado por el servidor, no por el cliente.

Un input malicioso como `1'; DROP TABLE items;--` se trata como **dato**, no como
código SQL. Además, `http://` no interviene: el input llega por JSON y se mapea a
parámetros.

### Recomendación

Mantener la práctica de usar **siempre** sentencias preparadas. No usar nunca
`$pdo->query($sqlUsuario)` ni interpolar variables en SQL.

### Pasos

1. No cambiar nada de la implementación actual.
2. Si algún día se agrega búsqueda con `LIKE`, seguir usando parámetros:
   `WHERE nombre LIKE :q` y pasar `'%' . $query . '%'`.
3. Considerar una regla de revisión de código: prohibir `$pdo->query()` con
   contenido variable.

---

## 3. Canal seguro / Transporte (contexto — HTTPS)

### Estado actual: ⚠️ El bloque de HTTPS es código muerto

**Análisis** (`public/index.php:57-77`): El intento de obligar HTTPS es inefectivo:

```php
if (defined('INTERNAL_SECRET')) {          // línea 57
    ...
}
...
define('INTERNAL_SECRET', $internalSecretEnv);   // línea 76 (se define DESPUÉS)
```

En cada request del built-in server el script se ejecuta de arriba hacia abajo:
en la línea 57 la constante **todavía no existe**, así que el bloque nunca corre.
Además, `https-proxy.php` solo envía `X-Forwarded-Proto: https` al worker
(`https-proxy.php:116`) y **no** `X-Internal-Secret`, por lo que aun reordenando
la definición la firma no coincidiría → siempre 403.

**Consecuencia práctica:** la API sirve tanto por `https://localhost:8443` como
por `http://localhost:8000` (y por el worker `http://127.0.0.1:8080` sin pasar
por el proxy), sin distinguir el canal.

### Recomendación

Decidir la política de canal:
- **Demo / clase:** documentar que el worker interno (`8080`) no debe exponerse y
  que el acceso público debe ir por el proxy TLS (`8443`). No exponer el puerto 8080.
- **Producción:** delegar el redirect HTTPS a Apache/Nginx y eliminar esta lógica
  casera.

### Pasos (recomendación mínima para demo)

1. En `serve-https.cmd` el worker escucha en `127.0.0.1:8080`: ya no es
   alcanzable desde otras máquinas. ✔ (cumplido hoy)
2. Verificar que el puerto del worker no quede abierto hacia afuera
   (`Get-NetTCPConnection -LocalPort 8080` → `LocalAddress` debe ser `127.0.0.1`).
3. Para producción: quitar el bloque de `public/index.php:45-77` y usar el redirect
   del servidor web (Nginx `return 301 https://$host$request_uri;` o Apache) y
   cabecera `Strict-Transport-Security`.

---

## 4. XSS (Cross-Site Scripting)

### Estado actual: ⚠️ Bajo riesgo — la API es JSON pura

**Análisis:** La API **no genera HTML**. Todas las respuestas se emiten como JSON
con `json_encode` (`public/index.php:192-195`), y el único `Content-Type` que se
envía es `application/json; charset=utf-8` (`public/index.php:43`). Sin HTML de
salida, no hay superficie clásica de reflejo/almacenamiento de XSS **dentro de la
API misma**.

**Riesgos reales:**

1. **Almacenamiento de HTML/scripts en la BD.** La validación de `nombre` en
   `ItemService::validate()` acepta cualquier texto (p. ej. `<script>alert(1)</script>`
   o `<img onerror=...>`). Se guarda tal cual. Si **otra** aplicación/sitio
   consume esta API y renderiza `nombre` como HTML sin escapar, se produce XSS
   **almacenado** (el atacante lo escribe una vez y el frontend lo ejecuta).
2. **Respuesta 500 filtra detalles.** `public/index.php:186-189` devuelve
   `$e->getMessage()` en `detail`. Un error de PDO puede exponer rutas, SQL o
   estructura (información sensible para un atacante).
3. **Login devuelve el username** sin escapar (`AuthService` → `user`). Mismo
   riesgo que el punto 1 si se pinta en HTML.

### Recomendación

- **Defensa en profundidad:** sumar cabeceras de seguridad que atenúen XSS si la
  API llegara a servir contenido.
- **Tratar la salida como insegura en el cliente:** el consumidor de la API debe
  escapar (`htmlspecialchars`) antes de pintar en HTML.
- **No exponer errores internos** al cliente en 500.

### Pasos

1. Añadir cabeceras de seguridad en `public/index.php` (junto al `Content-Type`):
   ```php
   header('Content-Type: application/json; charset=utf-8');
   header('X-Content-Type-Options: nosniff');
   header('X-Frame-Options: DENY');
   header('Referrer-Policy: no-referrer');
   header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
   ```
2. No devolver `$e->getMessage()` en el 500; registrar en un log y responder un
   mensaje genérico:
   ```php
   error_log($e->__toString());                 // log interno
   $response = new JsonResponse(500, ['error' => 'Error interno del servidor.']);
   ```
3. Si se quiere blindar el dato, sanitizar `nombre` en el servicio rechazando tags:
   ```php
   if (preg_match('/<[^>]*>/', $nombre)) {
       $errors['nombre'][] = 'El nombre no puede contener etiquetas HTML.';
   }
   ```
4. Documentar en el README que el **frontend debe escapar** todo lo que pinte
   (`htmlspecialchars($item['nombre'], ENT_QUOTES, 'UTF-8')`).

---

## 5. CSRF (Cross-Site Request Forgery)

### Estado actual: ⚠️ Bajo riesgo con la arquitectura actual, pero sin defensa propia

**Análisis:** CSRF explota la **autenticación implícita por cookies**: un sitio
malicioso hace que el navegador de la víctima envíe una petición a la API y el
carro de la cookie viaja solo. Esta API **no usa cookies** para autenticar: exige
`Authorization: Bearer <JWT>` en el encabezado (`public/index.php:99-113`), y un
JWT del `server-side` no va en cookies.

**Matiz importante:** un bloqueo de CORS *restringe leer la respuesta* pero **no
impide** que un navegador envíe una petición "simple" (POST con `Content-Type:
text/plain` — aunque esta API exige `application/json`, que **sí** dispara
preflight). Aun así, sin cookie en juego el navegador no adjunta el JWT
automáticamente, por lo que la petición cross-origin carece de credenciales y
fracasaría en 401.

### Recomendación

- Mantener el esquema **token en `Authorization`**, nunca migrar el JWT a cookie
  sin protegerla.
- Si en el futuro se usa cookie de sesión, implementar doble sumisión o tokens
  CSRF.
- **Origen estricto:** validar el encabezado `Origin`/`Referer` en peticiones
  sensibles (`POST/PUT/DELETE`) como capa extra.

### Pasos

1. (Hoy) No cambiar nada si el frontend consume la API con JWT en `Authorization`.
2. Si se mueve el JWT a cookie (p. ej. `HttpOnly`), entonces SÍ hay riesgo CSRF.
   Para ese caso:
   ```php
   // En el trabajo, validar un token anti-CSRF o el Origin
   $allowed = ['https://miapp.com'];
   $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
   if (!in_array($origin, $allowed, true)) {
       throw new UnauthorizedException('Origen no permitido.');
   }
   ```
3. Para métodos que cambian estado, exigir que vengan con `application/json`
   (ya ocurre porque `jsonBody()` falla si no es objeto JSON).

---

## 6. CORS (Cross-Origin Resource Sharing)

### Estado actual: ⚠️ Sin política definida

**Análisis:** La API **no emite cabeceras CORS** (no hay `Access-Control-Allow-*`
en `public/index.php` ni en el proxy). Consecuencias:

- Un frontend en `http://localhost:3000` (diferente origen que `http://localhost:8000`)
  podrá hacer peticiones "simples" GET, pero **leerá la respuesta bloqueada** y los
  `POST` con `application/json` **fallarán en el preflight**.
- Por omisión el navegador aplica el mismo origen (más restrictivo) → más seguro,
  pero rompe apps SPA y el flujo de la demo si se consume desde otro puerto.

### Recomendación

Definir la lista de **orígenes permitidos** explícita y no usar `*` en producción
(con `*` no se pueden usar credenciales/cookies y es demasiado permisivo). Para
una demo en localhost, permitir los puertos que se usen.

### Pasos

1. Agregar en `public/index.php` (antes del enrutado) un manejador CORS:
   ```php
   $allowedOrigins = [
       'http://localhost:3000',   // frontend dev de la demo
       'http://localhost:5173',   // Vite por defecto
   ];
   $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
   if (in_array($origin, $allowedOrigins, true)) {
       header("Access-Control-Allow-Origin: $origin");
       header('Access-Control-Allow-Credentials: true'); // solo si usás cookies
       header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
       header('Access-Control-Allow-Headers: Content-Type, Authorization');
   }

   // Responder el preflight OPTIONS sin procesarlo como 404
   if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
       http_response_code(204);
       exit;
   }
   ```
2. Si es **público** sin credenciales, se puede usar `*`:
   `header('Access-Control-Allow-Origin: *');` (no usar junto a `Credentials`).
3. Para datos poco sensibles, mantener un allowlist y nunca reflejar el `Origin`
   sin validar (evita reflejar un origen malicioso).

---

## 7. Hallazgos adicionales de buena higiene

| # | Hallazgo | Ubicación | Impacto | Recomendación |
|---|---|---|---|---|
| 7.1 | Secreto JWT por defecto fijo y en el código | `index.php:75,130` | **Crítico** si se despliega sin `JWT_SECRET` (forja de tokens) | Exigir `JWT_SECRET` en producción: fallar si no está definido |
| 7.2 | Error 500 expone `detail` del mensaje interno | `index.php:186-189` | Medio (fuga de información) | Log interno + respuesta genérica |
| 7.3 | El worker interno `8080` acepta cualquier Host | `serve-https.cmd` | Bajo (solo localhost) | Mantener en `127.0.0.1` y no abrirlo |
| 7.4 | Sin `Content-Type-Options: nosniff` ni seguridad de transporte | `index.php:43` | Bajo | Ver §4 paso 1 |
| 7.5 | Certificado autofirmado (sin CA pública) | `certs/` | Medio en producción (confianza del cliente) | Para producción: cert de CA (Let's Encrypt) |
| 7.6 | No hay límite de intentos de login (fuerza bruta) | `AuthService::login()` | Medio | Rate limiting / delay exponencial |
| 7.7 | No hay log de auditoría de accesos | — | Bajo | Registrar login fallidos/exitosos |
| 7.8 | JWT no tiene `nbf`/`jti`/validación de audiencia | `JwtService` | Bajo | Añadir `aud` y `jti` para revocación |

### Pasos para 7.1 (recomendación importante)

En `public/index.php` (Composition Root):

```php
$secret = getenv('JWT_SECRET');
if ($secret === false || $secret === '') {
    // En producción debería abortar; para la demo se deja un default.
    error_log('ADVERTENCIA: JWT_SECRET no definido; usando valor de desarrollo.');
    $secret = 'secreto-solo-para-desarrollo-cambiar';
}
```

Y en `make`/`serve-https.cmd`, documentar:

```cmd
set JWT_SECRET=un-secreto-largo-y-aleatorio  ^(antes de serve-https.cmd^)
```

---

## 8. Plan de aplicación priorizado

| Prioridad | Tarea | Sección |
|---|---|---|
| P1 (Crítica) | Obligar/rotar `JWT_SECRET` en cualquier despliegue | §7.1 |
| P1 (Crítica) | No exponer el worker interno `8080` fuera de `127.0.0.1` | §3, 7.3 |
| P2 (Alta) | No emitir `detail` interno en el 500; loguear | §4.2, 7.2 |
| P2 (Alta) | Decidir política CORS y aplicarla (allowlist) | §6 |
| P3 (Media) | Cabeceras de seguridad (nosniff, CSP, frame, referrer) | §4.1 |
| P3 (Media) | Protección básica fuerza bruta en login | §7.6 |
| P4 (Baja) | Validación `Origin` en métodos de estado (defensa extra) | §5.2 |
| P4 (Baja) | Claims extra en JWT (`aud`, `jti`) | §7.8 |

### Orden de ejecución sugerido

1. **Antes de desplegar:** corregir 7.1 (secret) y 7.3 (worker interno).
2. **Tras pasar a producción:** corregir 7.2 (errores), §6 (CORS) y 7.6
   (rate limiting).
3. **Mejora continua:** §4 cabeceras, §5.2, santizar `nombre`.

---

## 9. Conclusión

- **SQL Injection:** correctamente mitigada (preparadas de PDO). No requiere acción.
- **XSS:** la API no emite HTML (riesgo bajo); mitigar con cabeceras y tratamiento
  de errores, y exigir escapado en el frontend.
- **CSRF:** bajo riesgo con autenticación por `Authorization` Bearer; validar
  `Origin` si se añade cookie.
- **CORS:** no definido; elegir una política explícita (allowlist) para habilitar
  el consumo desde otro origen.
- **Debe corregirse antes de producción:** secreto JWT por defecto y exportación
  del worker interno.

El código es un excelente punto de partida didáctico porque ya aplica las buenas
prácticas de persistencia (preparadas, hashes bcrypt, interfaces) y de
autenticación (JWT firmado). Las recomendaciones de esta auditoría lo llevarían a
un estado más cercano a producción.
