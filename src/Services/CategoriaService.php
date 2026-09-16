<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Categoria;
use App\Models\Item;
use App\Repositories\CategoriaRepositoryInterface;
use App\Repositories\ItemRepositoryInterface;

/*
 * Capa de SERVICIO del caso de uso "categorias".
 *
 * La relación 1:N con Item se refleja aquí en tres puntos:
 *   - listAll()/getById() adjuntan un "items_count" (cuántos items tiene).
 *   - listItems(id) devuelve los items de la categoria (cara "N").
 *   - delete(id) se bloquea si la categoria aún tiene items (integridad
 *     referencial a nivel de negocio, error 422 en lugar de un crash SQL).
 */
final class CategoriaService
{
    public function __construct(
        private readonly CategoriaRepositoryInterface $repository,
        private readonly ItemRepositoryInterface $items,
    ) {
    }

    /** GET /categorias -> lista completa con su items_count. */
    public function listAll(): array
    {
        return array_map(
            fn (Categoria $categoria): array => $this->withCount($categoria),
            $this->repository->findAll(),
        );
    }

    /** GET /categorias/{id} -> 200 con items_count | 404. */
    public function getById(int $id): array
    {
        return $this->withCount($this->requireCategoria($id));
    }

    /** GET /categorias/{id}/items -> todos los items de la categoria (lado "N"). */
    public function listItems(int $id): array
    {
        $this->requireCategoria($id);

        return array_map(
            static fn (Item $item): array => $item->toArray(),
            $this->items->findByCategoria($id),
        );
    }

    /** GET /categorias/{id}/items?page=&per_page= -> { data, meta } paginado. */
    public function listItemsPaginated(int $id, int $page, int $perPage): array
    {
        $this->requireCategoria($id);

        $total       = $this->items->countByCategoria($id);
        $totalPages  = max(1, (int) ceil($total / $perPage));
        $page        = min($page, $totalPages);

        $offset = ($page - 1) * $perPage;
        $items  = array_map(
            static fn (Item $item): array => $item->toArray(),
            $this->items->findPageByCategoria($id, $offset, $perPage),
        );

        return [
            'data' => $items,
            'meta' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /** POST /categorias -> valida, aplica reglas y crea. */
    public function create(array $data): array
    {
        $nombre = $this->validate($data);
        $this->assertNameAvailable($nombre);

        return $this->withCount($this->repository->create(new Categoria(null, $nombre)));
    }

    /** PUT /categorias/{id} -> 404 si no existe; respeta regla de nombre único. */
    public function update(int $id, array $data): array
    {
        $this->requireCategoria($id);
        $nombre = $this->validate($data);

        $other = $this->repository->findByName($nombre);
        if ($other !== null && $other->getId() !== $id) {
            throw new ValidationException([
                'nombre' => ["Ya existe otra categoria con el nombre '{$nombre}'."],
            ]);
        }

        return $this->withCount($this->repository->update(new Categoria($id, $nombre)));
    }

    /** DELETE /categorias/{id} -> 404 si no existía; 422 si tiene items. */
    public function delete(int $id): void
    {
        $this->requireCategoria($id);

        if ($this->items->countByCategoria($id) > 0) {
            throw new ValidationException([
                'categoria_id' => ['No se puede eliminar la categoria: tiene items asociados.'],
            ]);
        }

        if (!$this->repository->delete($id)) {
            throw new NotFoundException("No existe la categoria con id {$id}.");
        }
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    private function requireCategoria(int $id): Categoria
    {
        $categoria = $this->repository->findById($id);
        if ($categoria === null) {
            throw new NotFoundException("No existe la categoria con id {$id}.");
        }

        return $categoria;
    }

    private function assertNameAvailable(string $nombre): void
    {
        if ($this->repository->findByName($nombre) !== null) {
            throw new ValidationException([
                'nombre' => ["Ya existe una categoria con el nombre '{$nombre}'."],
            ]);
        }
    }

    /** Adjunta el conteo de items (lado "1" de la relación 1:N). */
    private function withCount(Categoria $categoria): array
    {
        $data = $categoria->toArray();
        $data['items_count'] = $this->items->countByCategoria($categoria->getId() ?? -1);

        return $data;
    }

    /**
     * Valida "nombre" y devuelve el valor normalizado.
     * Reglas: obligatorio, texto recortado, entre 1 y 100 caracteres.
     */
    private function validate(array $data): string
    {
        $errors = [];

        if (!array_key_exists('nombre', $data)) {
            $errors['nombre'][] = 'El nombre es obligatorio.';
        } elseif (!is_string($data['nombre'])) {
            $errors['nombre'][] = 'El nombre debe ser texto.';
        } else {
            $nombre = trim($data['nombre']);
            if ($nombre === '') {
                $errors['nombre'][] = 'El nombre no puede estar vacío.';
            } elseif (mb_strlen($nombre) > 100) {
                $errors['nombre'][] = 'El nombre no puede superar los 100 caracteres.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return trim((string) $data['nombre']);
    }
}