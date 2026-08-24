# API de Items — Ejemplo didáctico (Programación 4)

API REST en PHP puro (sin frameworks) con un CRUD completo para la entidad **Item**
(`id`, `nombre`, `precio`). Su objetivo es demostrar, de forma mínima y legible,
la separación de responsabilidades en capas y el uso de interfaces para desacoplar
el almacenamiento.

## Conceptos que demuestra

| Concepto | Dónde mirar |
|---|---|
| Separación de responsabilidades: Controlador / Servicio / Repositorio | `src/Controllers`, `src/Services`, `src/Repositories` |
| Validadores como lógica de negocio dentro del servicio | `src/Services/ItemService.php` (`validate()`) |
| Interface para repositorios | `src/Repositories/ItemRepositoryInterface.php` |
| PDO con SQLite en archivo (persistente) | `src/Repositories/SqliteItemRepository.php` |
| PDO con SQLite en memoria (`sqlite::memory:`) | `src/Repositories/InMemoryItemRepository.php` |

Cada capa tiene una única responsabilidad:

```
HTTP  ->  public/index.php        Front controller: ruteo, composición de dependencias,
          |                       manejo global de excepciones y respuesta JSON.
          v
          ItemController          Traduce HTTP <-> dominio (códigos 200/201/204).
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

Punto clave: el servicio recibe por constructor **cualquier** implementación de la
interface. Cambiar de base de datos se decide en un solo lugar
(`public/index.php`), sin tocar controlador ni servicio.

## Requisitos

- PHP >= 8.1 con la extensión `pdo_sqlite` (incluida por defecto en Windows).

```powershell
php -v                 # verificar versión
php -m | Select-String sqlite   # verificar pdo_sqlite
```

No se necesita Composer ni bases de datos instaladas: el archivo SQLite se crea solo
en `data/items.sqlite` al primer arranque (con datos de ejemplo si está vacío).

## Cómo iniciarlo

Servidor de desarrollo de PHP desde la raíz del proyecto:

```powershell
php -S localhost:8000 -t public
```

Con repositorio **en memoria** (los datos se pierden al detener el servidor):

```powershell
$env:REPOSITORY_DRIVER='memory'; php -S localhost:8000 -t public    # PowerShell
REPOSITORY_DRIVER=memory php -S localhost:8000 -t public            # Linux/macOS
```

Valores posibles de `REPOSITORY_DRIVER`: `sqlite` (default) | `memory`.

## Endpoints

| Método | Ruta           | Éxito | Errores |
|--------|----------------|-------|---------|
| GET    | `/items`       | 200 lista | — |
| GET    | `/items/{id}`  | 200 item  | 404, 422 (id inválido) |
| POST   | `/items`       | 201 creado | 422 (validación) |
| PUT    | `/items/{id}`  | 200 actualizado | 404, 422 |
| DELETE | `/items/{id}`  | 204 sin cuerpo | 404, 422 |

## Cómo testearlo

Con el servidor corriendo, usar `curl` (o cualquier cliente REST). Ejemplos probados:

```powershell
# Listar todos
curl http://localhost:8000/items

# Obtener uno
curl http://localhost:8000/items/1

# Crear  -> 201
curl -X POST -H "Content-Type: application/json" `
     -d '{"nombre":"Lampara LED","precio":12.5}' `
     http://localhost:8000/items

# Nombre duplicado -> 422
curl -X POST -H "Content-Type: application/json" `
     -d '{"nombre":"TECLADO MECANICO","precio":30}' `
     http://localhost:8000/items

# Datos inválidos (sin nombre, precio negativo) -> 422 con detalle por campo
curl -X POST -H "Content-Type: application/json" `
     -d '{"precio":-5}' `
     http://localhost:8000/items

# Actualizar -> 200
curl -X PUT -H "Content-Type: application/json" `
     -d '{"nombre":"Lampara LED RGB","precio":19.99}' `
     http://localhost:8000/items/4

# Inexistente -> 404
curl -X PUT -H "Content-Type: application/json" `
     -d '{"nombre":"X","precio":1}' `
     http://localhost:8000/items/999

# Eliminar -> 204 sin cuerpo
curl -X DELETE http://localhost:8000/items/4
```

Respuestas de error esperadas:

```json
// 404
{ "error": "No existe el item con id 999." }

// 422 — los validadores del servicio acumulan TODOS los errores
{
    "error": "La solicitud contiene datos inválidos.",
    "errors": {
        "nombre": ["El nombre es obligatorio."],
        "precio": ["El precio no puede ser negativo."]
    }
}
```

### Prueba rápida de que las implementaciones son intercambiables

1. Con `REPOSITORY_DRIVER=sqlite` crear un item → detener el servidor → volver a
   iniciar: el item sigue ahí (archivo `data/items.sqlite`).
2. Con `REPOSITORY_DRIVER=memory` hacer lo mismo: cada arranque empieza con los 3
   items de ejemplo, porque la BD vive solo en RAM.
3. Ni el controlador ni el servicio cambiaron ni una línea.

### Verificación manual sin servidor

También puede validarse la lógica directamente por CLI:

```powershell
php -r "require 'src/bootstrap.php'; var_dump(class_exists('App\Services\ItemService'));"
```

## Reglas de negocio (servicio)

- `nombre`: obligatorio, texto, entre 1 y 100 caracteres, único (comparación sin
  distinción de mayúsculas/minúsculas ASCII).
- `precio`: obligatorio, numérico, mayor o igual que 0.
- Operar sobre un `id` inexistente produce 404; sobre un `id` mal formado, 422.

> Nota: `lower()` de SQLite solo convierte a minúsculas caracteres ASCII; por eso
> los datos sembrados evitan tildes ("mecanico", "inalambrico") y así la regla de
> nombre único se comporta de forma predecible en ambas implementaciones.
