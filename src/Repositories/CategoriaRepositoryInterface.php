<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Categoria;

/*
 * CONTRATO de acceso a datos para la entidad Categoria (CRUD).
 * Mismo patrón que ItemRepositoryInterface: el servicio depende SOLO de
 * esta interface y la implementación concreta se elige en el Composition Root.
 */
interface CategoriaRepositoryInterface
{
    /** @return Categoria[] todas las categorias */
    public function findAll(): array;

    /** Devuelve la categoria con ese id o null si no existe. */
    public function findById(int $id): ?Categoria;

    /** Devuelve la categoria con ese nombre (ignorando mayúsculas) o null. */
    public function findByName(string $nombre): ?Categoria;

    /** Inserta la categoria y devuelve una NUEVA instancia con el id asignado. */
    public function create(Categoria $categoria): Categoria;

    /** Actualiza el nombre de la categoria con ese id; devuelve la entidad. */
    public function update(Categoria $categoria): Categoria;

    /** Elimina la categoria; true si existía y se eliminó, false en caso contrario. */
    public function delete(int $id): bool;
}