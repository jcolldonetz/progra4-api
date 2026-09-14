# API de Items — Ejemplo didáctico (Programación 4)

API REST en PHP puro (sin frameworks) con CRUD para las entidades **Item**
(`id`, `nombre`, `precio`, `categoria_id`) y **Categoria** (`id`, `nombre`),
relacionadas **1:N** (cada item pertenece a una categoría opcional). Incluye
**autenticación por JWT** (`POST /login`).
Su objetivo es demostrar, de forma mínima y legible, la separación de
responsabilidades en capas, el uso de interfaces para desacoplar el
almacenamiento y las buenas prácticas básicas de seguridad.

## Conceptos que demuestra

| Concepto | Dónde mirar |
|---|---|
| Separación de responsabilidades: Controlador / Servicio / Repositorio | `src/Controllers`, `src/Services`, `src/Repositories` |
| Validadores como lógica de negocio dentro del servicio | `src/Services/ItemService.php`, `src/Services/AuthService.php` |
| Interface para repositorios | `ItemRepositoryInterface.php`, `CategoriaRepositoryInterface.php`, `UserRepositoryInterface.php` |
| Relación 1:N (Item ↔ Categoria) | `ItemService` valida `categoria_id`; `CategoriaService` adjunta `items_count` y bloquea delete si tiene items |
| PDO con SQLite en archivo (persistente) | `SqliteItemRepository.php`, `SqliteCategoriaRepository.php`, `SqliteUserRepository.php` |
| PDO con SQLite en memoria (`sqlite::memory:`) | `InMemoryItemRepository.php`, `InMemoryUserRepository.php` |
| Autenticación con JWT (HS256 implementado a mano) | `src/Security/JwtService.php` |
| Contraseñas hasheadas (bcrypt), nunca en texto plano | `password_hash()` al sembrar, `password_verify()` al loguear |
| Autorización Bearer en el front controller | `public/index.php` (`requireBearerToken()`) |
| HTTPS local con proxy TLS en PHP puro | `https-proxy.php`, `serve-https.cmd` |

Cada capa tiene una única responsabilidad:

```
HTTP  ->  public/index.php        Front controller: ruteo, composición,
          |                       verificación del JWT Bearer y respuesta JSON.
          v
   [POST /login]  AuthController -> AuthService -> UserRepositoryInterface
                                                  (verifica hash bcrypt, emite JWT)
          v
          ItemController          Traduce HTTP <-> dominio (200/201/204).
                                  Solo se alcanza si el token es válido.
          v
          ItemService             Lógica de negocio: validaciones y reglas
                                  (campos obligatorios, precio >= 0, nombre único).
          v
          ItemRepositoryInterface Contrato de acceso a datos.
          ^             ^
          |             |
   SqliteItemRepo  InMemoryItemRepo     Dos implementaciones intercambiables.
   (archivo)       (:memory:)           El resto del código no cambia.
```

## Estructura

```
progra4_clase3/
├── public/index.php                  Front controller (rutas + auth + composición)
├── openapi.yaml                      Spec OpenAPI importable en Postman/Swagger
├── seed_items.php                    Seeder de items de demostración (ver abajo)
├── https-proxy.php                   Proxy reverso TLS en PHP puro (levanta HTTPS)
├── serve-https.cmd                   Arranca la API en https://localhost:8443
├── make-cert.cmd                     Genera el certificado autofirmado (una vez)
├── certs/                            Certificados generados por make-cert.cmd
├── data/items.sqlite                 BD SQLite (se crea sola al primer arranque)
└── src/
    ├── bootstrap.php                 Autoloader PSR-4 sin Composer
    ├── Controllers/                  AuthController, ItemController
    ├── Services/                     AuthService (login/JWT), ItemService (validadores)
    ├── Repositories/                 Interfaces + implementaciones SQLite archivo/memoria
    ├── Models/                       Item, User (el hash nunca sale en toArray())
    ├── Security/JwtService.php       Emisión/verificación JWT HS256
    ├── Exceptions/                   ApiException base -> 401, 404, 422
    ├── Database/PdoFactory.php       Conexiones PDO uniformes
    └── Http/JsonResponse.php         Respuesta JSON mínima
```

## Requisitos

- PHP >= 8.1 con la extensión `pdo_sqlite` (incluida por defecto en Windows).

```powershell
php -v                          # verificar versión
php -m | Select-String sqlite   # verificar pdo_sqlite
```

No se necesita Composer ni motor de BD instalado.

## Cómo iniciarlo

```powershell
php -S localhost:8000 -t public
```

### HTTPS (sin instalar nada)

El servidor built-in de PHP en **Windows no soporta TLS** (el flag `--cert` arroja
`Unsupported SSL request`). Esta demo incluye un **proxy reverso TLS en PHP puro**
(`stream_socket_server` con contexto SSL) que descifra HTTPS y reenvía al worker HTTP:

1. Generar un certificado autofirmado **una sola vez**:
   ```
   make-cert.cmd        → crea certs\server.crt y certs\server.key
   ```
2. Levantar la API por HTTPS:
   ```
   serve-https.cmd      → https://localhost:8443  (driver sqlite)
   serve-https.cmd memory   → driver en memoria
   serve-https.cmd sqlite 9000  → puerto custom
   ```

Esto arranca dos procesos: el worker del server PHP en `http://127.0.0.1:8080`
(plano) y el proxy TLS en `https://localhost:8443`. El navegador/curl mostrarán
una advertencia de "certificado no confiable" (es autofirmado); en pruebas se
acepta, y `curl` usa `-k` (insecure). En producción se usaría Nginx/Caddy/Apache
o un túnel con CA pública.

> `make-cert.cmd` detecta `openssl.exe` del PATH o el de `C:\xampp\apache\bin`.
> Solo escucha en `127.0.0.1`, por lo que no se expone a la red local.

Variables de entorno opcionales:

| Variable | Valores | Default | Uso |
|---|---|---|---|
| `REPOSITORY_DRIVER` | `sqlite` \| `memory` | `sqlite` | Archivo persistente o BD en RAM |
| `JWT_SECRET` | texto | solo desarrollo | Secreto de firma HMAC |
| `JWT_TTL_SECONDS` | número | `3600` | Vigencia del token |

```powershell
# Ejemplo: memoria + secreto propio
$env:REPOSITORY_DRIVER='memory'; $env:JWT_SECRET='mi-secreto'; php -S localhost:8000 -t public
```

### Probar por HTTPS

```powershell
$resp = curl.exe -s -k -X POST -H "Content-Type: application/json" `
     -d '{"username":"admin","password":"1234"}' `
     https://localhost:8443/login
$token = ($resp | ConvertFrom-Json).token
curl.exe -s -k -H "Authorization: Bearer $token" https://localhost:8443/items
```

## Seeder de datos de demostración

`seed_items.php` puebla la tabla `items` con productos de informática
(Monitores, Auriculares, Mouses, Teclados, Impresoras, Toners, Cartuchos de
Tinta y Notebooks), combinando categoría + marca + modelo. Genera nombres
**únicos** (case-insensitive, como exige `ItemService`) y precios realistas por
categoría.

```powershell
php seed_items.php               # 1000 items (por defecto)
php seed_items.php 500           # 500 items
php seed_items.php 12345 --reset # borra los existentes y crea 12 345
```

- **`cantidad`** (opcional): entero positivo; cuántos items insertar. Si el
  catálogo no alcanza, se rellena con variaciones genéricas hasta completarlo.
- **`--reset`** (opcional): vacía la tabla `items` antes de insertar. Sin este
  flag, los items se **agregan** a los existentes.

Uso típico en clase para mostrar que **la UI se vuelve lenta cuando la API
devuelve toda la lista sin límite ni paginado**:

```powershell
php seed_items.php 1000 --reset
php -S localhost:8000 -t public
# ingresa en http://localhost:8000 (admin / 1234) y observa el panel:
# GET /items responde 1000 filas y el frontend las renderiza todas de una vez.
```

> El seeder escribe directo en `data/items.sqlite` con PDO (sin pasar por el
> servicio), así el insert es masivo y veloz. El repositorio solo siembra sus 3
> ejemplos si la tabla está vacía, por lo que no interfiere con este script.

## Endpoints

| Método | Ruta | Auth | Éxito | Errores |
|--------|------|------|-------|---------|
| POST | `/login` | pública | 200 `{token, ...}` | 401, 422 |
| GET | `/items` | Bearer JWT | 200 lista | 401 |
| GET | `/items/{id}` | Bearer JWT | 200 item | 401, 404, 422 |
| POST | `/items` | Bearer JWT | 201 creado | 401, 422 |
| PUT | `/items/{id}` | Bearer JWT | 200 actualizado | 401, 404, 422 |
| DELETE | `/items/{id}` | Bearer JWT | 204 sin cuerpo | 401, 404, 422 |
| GET | `/categorias` | Bearer JWT | 200 lista (con `items_count`) | 401 |
| GET | `/categorias/{id}` | Bearer JWT | 200 categoria | 401, 404, 422 |
| GET | `/categorias/{id}/items` | Bearer JWT | 200 items de la categoria | 401, 404, 422 |
| POST | `/categorias` | Bearer JWT | 201 creada | 401, 422 |
| PUT | `/categorias/{id}` | Bearer JWT | 200 actualizada | 401, 404, 422 |
| DELETE | `/categorias/{id}` | Bearer JWT | 204 sin cuerpo | 401, 404, **422 si tiene items** |

## Cómo testearlo

### 1. Obtener token (credenciales sembradas: admin / 1234)

```powershell
$resp = curl.exe -s -X POST -H "Content-Type: application/json" `
     -d '{"username":"admin","password":"1234"}' `
     http://localhost:8000/login
$resp                                        # ver la respuesta completa
$token = ($resp | ConvertFrom-Json).token    # guardar el JWT
```

Respuesta:

```json
{
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": { "id": 1, "username": "admin" }
}
```

Casos de error: password incorrecta → **401** `{"error":"Credenciales inválidas."}`;
falta un campo → **422** con detalle por campo.

### 2. Consumir el CRUD enviando el token

```powershell
# Listar
curl.exe -H "Authorization: Bearer $token" http://localhost:8000/items

# Crear -> 201
curl.exe -X POST -H "Authorization: Bearer $token" -H "Content-Type: application/json" `
     -d '{"nombre":"Lampara LED","precio":12.5}' `
     http://localhost:8000/items

# Actualizar -> 200
curl.exe -X PUT -H "Authorization: Bearer $token" -H "Content-Type: application/json" `
     -d '{"nombre":"Lampara LED RGB","precio":19.99}' `
     http://localhost:8000/items/4

# Eliminar -> 204
curl.exe -X DELETE -H "Authorization: Bearer $token" http://localhost:8000/items/4
```

Sin token o con token inválido/expirado cualquier endpoint de items responde:

```json
// 401
{ "error": "Falta el encabezado Authorization: Bearer <token>." }
{ "error": "Token inválido o expirado." }
```

### 3. Probar con Postman

Importar `openapi.yaml` (**Import** → arrastrar el archivo). La colección incluye
la petición `iniciarSesion`; luego usar la pestaña **Authorization → Bearer Token**
pegando el token devuelto.

### 4. Demostraciones útiles para clase

- **Intercambiabilidad**: crear un item con driver `sqlite`, reiniciar y ver que
  persiste; repetir con `memory` y comprobar que cada arranque empieza limpio.
  Ni controlador ni servicio cambian una línea.
- **Hash en BD** (no hay texto plano):

  ```powershell
  php -r '$pdo = new PDO("sqlite:data/items.sqlite"); print_r($pdo->query("SELECT username, password_hash FROM users")->fetchAll());'
  # password_hash => $2y$12$... (bcrypt)
  ```

- **Expiración**: `$env:JWT_TTL_SECONDS='1'`, loguearse, esperar 2 s y llamar a
  `/items` → 401 `Token inválido o expirado.`

## Reglas de negocio (servicio)

Items:
- `nombre`: obligatorio, texto, entre 1 y 100 caracteres, único (case-insensitive ASCII).
- `precio`: obligatorio, numérico, mayor o igual que 0.
- `id` inexistente → 404; `id` mal formado → 422.

Autenticación:
- `username` y `password` obligatorios (422 si faltan).
- Credenciales incorrectas → 401 con mensaje genérico (no revela qué campo falló).

## Tests unitarios

La API usa **PHPUnit 12** (solo dependencia de desarrollo, vía Composer) para
probar las capas de servicios, seguridad, controladores, modelos y repositorios
de memoria. Los tests de servicios y repositorios corren sobre
`InMemoryItemRepository` / `InMemoryUserRepository` (SQLite en memoria), así que
no tocan el archivo `data/items.sqlite`.

```powershell
composer install          # primera vez: descarga PHPUnit
composer test             # o: php vendor/bin/phpunit
```

Los tests viven en `tests/` con el namespace `App\Tests\` y la configuración en
`phpunit.xml`. Cubren:

- **JwtService**: emisión/verificación, firma alterada, secreto distinto,
  algoritmo no permitido, token expirado, formatos inválidos.
- **AuthService**: login (válido, 401, campos faltantes), registro (duplicado,
  validaciones), acumulación de errores 422.
- **ItemService**: CRUD completo, reglas de negocio (nombre único
  case-insensitive, precio >= 0, longitud máxima) y códigos 404/422.
- **Controllers** (`ItemController`, `AuthController`): códigos HTTP (200/201/204),
  datos de respuesta y traducción de excepciones.
- **Modelos y repositorios en memoria**: serialización segura (sin
  `password_hash` en `toArray()`) y contrato CRUD.

## Seguridad aplicada

- **Contraseñas**: se guardan como hash bcrypt (`password_hash(..., PASSWORD_BCRYPT)`),
  nunca en texto plano; la comparación usa `password_verify()`.
- **JWT firmado HS256**: cualquier alteración del token invalida la firma
  (`hash_equals`, comparación en tiempo constante). Se valida también el `alg`
  declarado en el header y la vigencia (`exp`).
- **Secreto configurable**: `JWT_SECRET` por variable de entorno; el default es
  solo para desarrollo.
- El token viaja en el payload visible (no cifrado): no poner datos sensibles ahí;
  la seguridad está en la firma.

> Nota didáctica: `JwtService` está escrito a mano para mostrar cómo funciona un
> JWT por dentro. En producción conviene una librería mantenida
> (p. ej. `firebase/php-jwt`), además de refresh tokens y HTTPS.

> Nota sobre `lower()` de SQLite: solo minúsculas ASCII; por eso los datos
> sembrados evitan tildes ("mecanico") y así la regla de nombre único es predecible.
