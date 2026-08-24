<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/*
 * CONTRATO de acceso a datos para usuarios.
 * Igual que con los items: el servicio depende de esta interface y no sabe
 * si los usuarios viven en un archivo SQLite o en una BD en memoria.
 */
interface UserRepositoryInterface
{
    /** Devuelve el usuario cuyo username coincida exactamente, o null. */
    public function findByUsername(string $username): ?User;
}
