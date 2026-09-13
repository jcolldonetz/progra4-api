<?php

declare(strict_types=1);

/*
 * FRONT CONTROLLER: único punto de entrada HTTP de la API.
 *
 * Responsabilidades:
 *  1. Cargar el autoload (bootstrap).
 *  2. COMPOSICIÓN DE DEPENDENCIAS: elegir qué implementación de los
 *     repositorios usar e inyectarla en los servicios.
 *  3. Enrutar método HTTP + URL hacia el método del controlador.
 *  4. AUTORIZACIÓN: exigir un JWT válido (Bearer) para /items; /login es público.
 *  5. Convertir respuestas y excepciones en JSON con su código HTTP.
 *
 * Ejecutar:
 *   php -S localhost:8000 -t public
 * Con repositorio en memoria:
 *   $env:REPOSITORY_DRIVER='memory'; php -S localhost:8000 -t public   (PowerShell)
 *
 * Variables de entorno opcionales:
 *   REPOSITORY_DRIVER   sqlite | memory      (default: sqlite)
 *   JWT_SECRET          secreto de firma     (default solo para desarrollo)
 *   JWT_TTL_SECONDS     vigencia del token   (default: 3600)
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\ItemController;
use App\Exceptions\ApiException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Http\JsonResponse;
use App\Repositories\InMemoryItemRepository;
use App\Repositories\InMemoryUserRepository;
use App\Repositories\SqliteItemRepository;
use App\Repositories\SqliteUserRepository;
use App\Security\JwtService;
use App\Services\AuthService;
use App\Services\ItemService;

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------------
// 0a) CORS: el cliente web corre en un origen distinto al de la API (Vite).
//
//     BRECHA DE SEGURIDAD DE 'Access-Control-Allow-Origin: *':
//     Con ese valor, CUALQUIER página que el usuario visite (incluidas las
//     maliciosas) puede leer las respuestas de esta API. Si la sesión se guarda
//     en localStorage (como hace el cliente React), un sitio ajeno con acceso al
//     token podría hacer peticiones autenticadas en nombre del usuario.
//
//     Por eso esa línea queda COMENTADA y solo se refleja el origen HTTP si
//     pertenece a la lista blanca de abajo (el dev server de Vite).
// ---------------------------------------------------------------------------
// header('Access-Control-Allow-Origin: *');

$allowedOrigins = [
    'http://localhost:5173', // servidor de desarrollo Vite
    'http://127.0.0.1:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// 0) Exigir canal seguro (HTTPS). Solo se sirven peticiones cifradas.
//
//    El worker (php -S) siempre recibe HTTP plano: directamente o reenviado por
//    https-proxy.php. Para distinguir un reenvío legítimo del proxy de un
//    acceso HTTP directo:
//      - https-proxy.php inyecta el encabezado interno "X-Forwarded-Proto: https"
//        y una firma "X-Internal-Secret" igual al secreto compartido.
//      - Aquí se exigen AMBOS. El secreto (tomado de JWT_SECRET) no es visible
//        para un cliente externo, así que un HTTP directo sin pasar por el
//        proxy no puede forjarlo con éxito -> 403.
// ---------------------------------------------------------------------------
if (defined('INTERNAL_SECRET')) {
    $forwardedProto = strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $internalSecret = is_string($_SERVER['HTTP_X_INTERNAL_SECRET'] ?? null)
        ? $_SERVER['HTTP_X_INTERNAL_SECRET']
        : '';

    $viaProxy = $forwardedProto === 'https' && hash_equals(INTERNAL_SECRET, $internalSecret);

    if (!$viaProxy) {
        http_response_code(403);
        echo json_encode(
            ['error' => 'Solo se permite HTTPS. Use https:// seguido del host y puerto del servidor.'],
            JSON_UNESCAPED_UNICODE,
        );
        exit;
    }
}

$internalSecretEnv = getenv('JWT_SECRET') ?: 'secreto-solo-para-desarrollo-cambiar';
define('INTERNAL_SECRET', $internalSecretEnv);
unset($internalSecretEnv);

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

/**
 * "Middleware" de AUTORIZACIÓN: valida el encabezado
 *   Authorization: Bearer <jwt>
 * o, en su defecto, la cookie HttpOnly "access_token" que el navegador
 * envía automáticamente con cada petición.
 * Devuelve los claims del token o lanza 401 si falta, está alterado o expiró.
 *
 * @return array<string, mixed>
 */
function requireBearerToken(JwtService $jwt): array
{
    $token = null;

    // 1) Token de autenticación de la cookie HttpOnly emitida por la API
    //    (se envía sola con cada petición; JS no puede leerla).
    if (isset($_COOKIE['access_token'])) {
        $token = is_string($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;
    }

    // 2) El Bearer tiene prioridad si viene presente (modos localStorage/cookie JS).
    $header = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        $token = $m[1];
    }

    if ($token === null) {
        throw new UnauthorizedException(
            'Falta el encabezado Authorization: Bearer <token> (o la cookie de sesión access_token).'
        );
    }

    $claims = $jwt->verify($token);
    if ($claims === null) {
        throw new UnauthorizedException('Token inválido o expirado.');
    }

    return $claims;
}

// ---------------------------------------------------------------------------
// 1) Composición de dependencias (Composition Root)
// ---------------------------------------------------------------------------
$driver = strtolower(getenv('REPOSITORY_DRIVER') ?: 'sqlite');
$dbFile = dirname(__DIR__) . '/data/items.sqlite';

[$itemRepository, $userRepository] = match ($driver) {
    // Ambos repositorios comparten el mismo archivo SQLite (persistente)...
    'sqlite' => [new SqliteItemRepository($dbFile), new SqliteUserRepository($dbFile)],
    // ...o dos BD independientes en RAM (volátiles, se siembran en cada request).
    'memory' => [new InMemoryItemRepository(), new InMemoryUserRepository()],
    default  => throw new RuntimeException("REPOSITORY_DRIVER inválido: '{$driver}' (use 'sqlite' o 'memory')."),
};

$jwt = new JwtService(
    getenv('JWT_SECRET') ?: 'secreto-solo-para-desarrollo-cambiar',
    (int) (getenv('JWT_TTL_SECONDS') ?: 3600),
);

$authController = new AuthController(
    new AuthService($userRepository, $jwt),
    $jwt,
    $jwt->ttlSeconds(),
);
$itemController = new ItemController(new ItemService($itemRepository));

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
    // Rutas PÚBLICAS: login, registro y logout.
    if ($resource === 'login' && $method === 'POST' && $id === null) {
        $response = $authController->login(jsonBody());
    } elseif ($resource === 'register' && $method === 'POST' && $id === null) {
        $response = $authController->register(jsonBody());
    } elseif ($resource === 'logout' && $method === 'POST' && $id === null) {
        // Borra la cookie HttpOnly en el cliente (Max-Age=0). Idempotente.
        header('Set-Cookie: access_token=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
        $response = new JsonResponse(204, null);
    } elseif ($resource === 'me' && $method === 'GET' && $id === null) {
        // Protegido: acepta Bearer o cookie. Restaura la sesión de la cookie.
        $claims = requireBearerToken($jwt);
        $response = $authController->me($claims);
    } elseif ($resource === 'items') {
        // Rutas PROTEGIDAS: se corta aquí si el JWT no es válido (401),
        // antes de llegar al controlador. Los claims quedan disponibles.
        $claims = requireBearerToken($jwt);
        unset($claims); // el controlador actual no los necesita; podrían inyectarse

        $response = match (true) {
            $method === 'GET'    && $id === null => $itemController->index(),
            $method === 'POST'   && $id === null => $itemController->store(jsonBody()),
            $method === 'GET'    && $id !== null => $itemController->show($id),
            $method === 'PUT'    && $id !== null => $itemController->update($id, jsonBody()),
            $method === 'DELETE' && $id !== null => $itemController->destroy($id),
            default => new JsonResponse(404, ['error' => "Ruta no encontrada: {$method} /items" . ($id !== null ? "/{$id}" : '')]),
        };
    } else {
        $response = new JsonResponse(404, ['error' => "Ruta no encontrada: {$method} " . ($_SERVER['REQUEST_URI'] ?? '')]);
    }
} catch (ApiException $e) {
    // Excepciones de negocio -> códigos HTTP previstos (401, 404, 422).
    if ($e->httpStatus() === 401) {
        header('WWW-Authenticate: Bearer realm="api-items"');
    }

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
