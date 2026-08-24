<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/*
 * Fábrica centralizada de conexiones PDO.
 * Tanto el repositorio con archivo SQLite como el que usa la BD en memoria
 * pasan por aquí, garantizando la misma configuración en ambas implementaciones.
 */
final class PdoFactory
{
    public static function create(string $dsn): PDO
    {
        return new PDO($dsn, null, null, [
            // Lanzar excepciones ante errores SQL en lugar de silenciarlos.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Devolver filas como arrays asociativos por defecto.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
