# API de Items — Ejemplo didáctico (Programación 4)

API REST en PHP puro (sin frameworks) con CRUD para la entidad **Item**
(`id`, `nombre`, `precio`) y **autenticación por JWT** (`POST /login`).
Su objetivo es demostrar, de forma mínima y legible, la separación de
responsabilidades en capas, el uso de interfaces para desacoplar el
almacenamiento y las buenas prácticas básicas de seguridad.

## Conceptos que demuestra

| Concepto | Dónde mirar |
|---|---|
| Separación de responsabilidades: Controlador / Servicio / Repositorio | `src/Controllers`, `src/Services`, `src/Repositories` |
| Validadores como lógica de negocio dentro del servicio | `src/Services/ItemService.php`, `src/Services/AuthService.php` |
| Interface para repositorios | `ItemRepositoryInterface.php`, `UserRepositoryInterface.php` |
| PDO con SQLite en archivo (persistente) | `SqliteItemRepository.php`, `SqliteUserRepository.php` |
| PDO con SQLite en memoria (`sqlite::memory:`) | `InMemoryItemRepository.php`, `InMemoryUserRepository.php` |
| Autenticación con JWT (HS256 implementado a mano) | `src/Security/JwtService.php` |
| Contraseñas hasheadas (bcrypt), nunca en texto plano | `password_hash()` al sembrar, `password_verify()` al loguear |
| Autorización Bearer en el front controller | `public/index.php` (`requireBearerToken()`) |

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

## Endpoints

| Método | Ruta | Auth | Éxito | Errores |
|--------|------|------|-------|---------|
| POST | `/login` | pública | 200 `{token, ...}` | 401, 422 |
| GET | `/items` | Bearer JWT | 200 lista | 401 |
| GET | `/items/{id}` | Bearer JWT | 200 item | 401, 404, 422 |
| POST | `/items` | Bearer JWT | 201 creado | 401, 422 |
| PUT | `/items/{id}` | Bearer JWT | 200 actualizado | 401, 404, 422 |
| DELETE | `/items/{id}` | Bearer JWT | 204 sin cuerpo | 401, 404, 422 |

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
