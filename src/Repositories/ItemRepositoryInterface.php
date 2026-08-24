<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Item;

/*
 * CONTRATO de acceso a datos para la entidad Item (CRUD).
 *
 * El servicio depende SOLO de esta interface, nunca de una implementación
 * concreta. Así podemos intercambiar SQLite por archivo, SQLite en memoria,
 * MySQL, etc., sin tocar el servicio ni el controlador.
 */
interface ItemRepositoryInterface
{
    /** @return Item[] todos los items */
    public function findAll(): array;

    /** Devuelve el item con ese id o null si no existe. */
    public function findById(int $id): ?Item;

    /** Devuelve el item con ese nombre (ignorando mayúsculas) o null. */
    public function findByName(string $nombre): ?Item;

    /** Inserta el item y devuelve una NUEVA instancia con el id asignado. */
    public function create(Item $item): Item;

    /** Actualiza nombre y precio del item con ese id; devuelve la entidad. */
    public function update(Item $item): Item;

    /** Elimina el item; true si existía y se eliminó, false en caso contrario. */
    public function delete(int $id): bool;
}
