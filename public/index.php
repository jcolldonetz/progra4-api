<?php

declare(strict_types=1);

/*
 * FRONT CONTROLLER: único punto de entrada HTTP de la API.
 *
 * Responsabilidades:
 *  1. Cargar el autoload (bootstrap).
 *  2. COMPOSICIÓN DE DEPENDENCIAS: elegir qué implementación del repositorio
 *     usar e inyectarla en el servicio (aquí es donde se intercambia SQLite
 *     por archivo <-> SQLite en memoria, sin tocar el resto del código).
 *  3. Enrutar método HTTP + URL hacia el método del controlador.
 *  4. Convertir respuestas y excepciones en JSON con su código HTTP.
 *
 * Ejecutar:
 *   php -S localhost:8000 -t public
 * Con repositorio en memoria:
 *   $env:REPOSITORY_DRIVER='memory'; php -S localhost:8000 -t public   (PowerShell)
 *   REPOSITORY_DRIVER=memory php -S localhost:8000 -t public           (Linux/macOS)
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\ItemController;
use App\Exceptions\ApiException;
use App\Exceptions\ValidationException;
use App\Http\JsonResponse;
use App\Repositories\InMemoryItemRepository;
use App\Repositories\SqliteItemRepository;
use App\Services\ItemService;

header('Content-Type: application/json; charset=utf-8');

/** Decodifica el cuerpo JSON de la petición; lanza 422 si no es válido. */
function jsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new ValidationException(['body' => ['Se esperaba un objeto JSON válido.']]);
    }

    return $data;
}

// ---------------------------------------------------------------------------
// 1) Composición de dependencias (Composition Root)
// ---------------------------------------------------------------------------
$driver = strtolower(getenv('REPOSITORY_DRIVER') ?: 'sqlite');

$repository = match ($driver) {
    'sqlite' => new SqliteItemRepository(dirname(__DIR__) . '/data/items.sqlite'), // persistente
    'memory' => new InMemoryItemRepository(),                                      // volátil
    default  => throw new RuntimeException("REPOSITORY_DRIVER inválido: '{$driver}' (use 'sqlite' o 'memory')."),
};

$controller = new ItemController(new ItemService($repository));

// ---------------------------------------------------------------------------
// 2) Enrutado mínimo: /items y /items/{id}
// ---------------------------------------------------------------------------
$segments = array_values(array_filter(
    explode('/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
    static fn (string $s): bool => $s !== '' && $s !== 'index.php',
));
if (($segments[0] ?? '') === 'public') {
    array_shift($segments); // permite servir sin -t public
}

$resource = $segments[0] ?? '';
$id       = $segments[1] ?? null;
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $response = match (true) {
        $resource === 'items' && $method === 'GET'    && $id === null => $controller->index(),
        $resource === 'items' && $method === 'POST'   && $id === null => $controller->store(jsonBody()),
        $resource === 'items' && $method === 'GET'    && $id !== null => $controller->show($id),
        $resource === 'items' && $method === 'PUT'    && $id !== null => $controller->update($id, jsonBody()),
        $resource === 'items' && $method === 'DELETE' && $id !== null => $controller->destroy($id),
        default => new JsonResponse(404, ['error' => "Ruta no encontrada: {$method} " . ($_SERVER['REQUEST_URI'] ?? '')]),
    };
} catch (ApiException $e) {
    // Excepciones de negocio -> códigos HTTP previstos (404, 422).
    $payload = ['error' => $e->getMessage()];
    if ($e instanceof ValidationException) {
        $payload['errors'] = $e->getErrors();
    }
    $response = new JsonResponse($e->httpStatus(), $payload);
} catch (Throwable $e) {
    // Cualquier otro error -> 500.
    $response = new JsonResponse(500, [
        'error'  => 'Error interno del servidor.',
        'detail' => $e->getMessage(),
    ]);
}

http_response_code($response->status);
if ($response->status !== 204) {
    echo json_encode($response->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
}
