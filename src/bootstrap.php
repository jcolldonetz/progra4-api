<?php

declare(strict_types=1);

/*
 * Bootstrap de la aplicación.
 * Registra un autoloader simple (estilo PSR-4) que traduce namespaces "App\"
 * a carpetas dentro de src/, sin necesidad de Composer.
 *
 * Ejemplo: App\Repositories\ItemRepositoryInterface -> src/Repositories/ItemRepositoryInterface.php
 */
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
