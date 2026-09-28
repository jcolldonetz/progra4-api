<?php

declare(strict_types=1);

/*
 * Bootstrap de la aplicación.
 *
 * 1) CARGA DE CONFIGURACIÓN (.env): si existe un archivo .env en la raíz del
 *    proyecto, se leen sus variables y se publican en el entorno (getenv y
 *    $_ENV) para que el resto del código las vea con getenv(). Prioridad:
 *       variable de la terminal  >  .env  >  default del código.
 *    Es decir, una variable ya definida en el entorno real (p. ej. la que fija
 *    serve-https.cmd o un $env: en PowerShell) NO se pisa.
 *
 * 2) AUTOLOAD: si existe el autoloader de Composer (vendor/autoload.php) se usa
 *    ESE: cubre tanto los namespaces "App\" configurados en composer.json como
 *    las dependencias de terceros (p. ej. Predis\Client para el publisher
 *    realtime). Si no hay vendor/ (proyecto clonado sin "composer install"),
 *    se registra un autoloader simple (estilo PSR-4) que traduce namespaces
 *    "App\" a carpetas dentro de src/:
 *      Ejemplo: App\Repositories\ItemRepositoryInterface -> src/Repositories/ItemRepositoryInterface.php
 */

/** Carga variables "KEY=VALUE" de un archivo .env al entorno (sin pisar las
 * que ya estén definidas en la terminal). Implementación mínima, sin
 * dependencias externas (a propósito, para mantener la lección de PHP puro). */
function loadDotEnv(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        // Comentarios y línea vacía.
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // Permite el prefijo "export " (estilo bash) si alguien lo escribe.
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, strlen('export ')));
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue; // línea sin "=": se ignora
        }

        $key   = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if ($key === '') {
            continue;
        }

        // La terminal manda: si ya está definida afuera, la respetamos.
        if (getenv($key) !== false) {
            continue;
        }

        // Comentario en línea: "REDIS_URL=127.0.0.1:6379 # realtime".
        if (($hash = strpos($value, ' #')) !== false) {
            $value = trim(substr($value, 0, $hash));
        }

        // Quitar comillas simples o dobles si envuelven el valor completo.
        $length = strlen($value);
        if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"')
            || ($value[0] === "'" && $value[$length - 1] === "'"))) {
            $value = substr($value, 1, $length - 2);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

loadDotEnv(dirname(__DIR__) . '/.env');

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)))
        . '.php';

    if (is_file($file)) {
        require $file;
    }
});
