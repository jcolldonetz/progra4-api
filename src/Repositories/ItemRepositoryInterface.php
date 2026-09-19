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

    /** Página de items ordenados por id (para listado paginado). */
    public function findPage(int $offset, int $limit): array;

    /** Total de items del repositorio (para el meta de paginación). */
    public function countAll(): int;

    /** Página de items de la categoria indicada (para listado paginado). */
    public function findPageByCategoria(int $categoriaId, int $offset, int $limit): array;

    /** Devuelve el item con ese id o null si no existe. */
    public function findById(int $id): ?Item;

    /** Devuelve el item con ese nombre (ignorando mayúsculas) o null. */
    public function findByName(string $nombre): ?Item;

    /** @return Item[] los items de la categoria indicada (relación 1:N). */
    public function findByCategoria(int $categoriaId): array;

    /** Cantidad de items que pertenecen a la categoria indicada. */
    public function countByCategoria(int $categoriaId): int;

    /**
     * Página de items que coinciden con los filtros opcionales, ordenados por id.
     *
     * @param ?int    $categoriaId filtra por categoría (null = cualquier categoría)
     * @param ?string $search      texto parcial del nombre, sin distinguir mayúsculas
     *                             (null o '' = sin filtro de texto)
     */
    public function findPageFiltered(?int $categoriaId, ?string $search, int $offset, int $limit): array;

    /** Total de items que coinciden con los filtros opcionales. */
    public function countFiltered(?int $categoriaId, ?string $search): int;

    /** Inserta el item y devuelve una NUEVA instancia con el id asignado. */
    public function create(Item $item): Item;

    /** Actualiza nombre y precio del item con ese id; devuelve la entidad. */
    public function update(Item $item): Item;

    /** Elimina el item; true si existía y se eliminó, false en caso contrario. */
    public function delete(int $id): bool;
}
