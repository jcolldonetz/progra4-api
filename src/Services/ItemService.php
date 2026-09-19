<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Item;
use App\Repositories\CategoriaRepositoryInterface;
use App\Repositories\ItemRepositoryInterface;

/*
 * Capa de SERVICIO: concentra la LÓGICA DE NEGOCIO del caso de uso "items".
 *
 * - Depende de las INTERFACES ItemRepositoryInterface y
 *   CategoriaRepositoryInterface, no de implementaciones concretas.
 * - Aquí viven los VALIDADORES y las reglas de negocio (nombre único,
 *   precio no negativo, la categoria debe existir). El repositorio solo
 *   guarda/recupera datos.
 */
final class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $repository,
        private readonly CategoriaRepositoryInterface $categorias,
    ) {
    }

    /** GET /items -> lista completa. */
    public function listAll(): array
    {
        return array_map(static fn (Item $item) => $item->toArray(), $this->repository->findAll());
    }

    /**
     * GET /items?page=&per_page=&categoria_id=&q= -> { data, meta } con
     * paginación y filtros opcionales por categoría y texto del nombre.
     */
    public function listPaginated(int $page, int $perPage, ?int $categoriaId = null, ?string $search = null): array
    {
        if ($categoriaId !== null && $this->categorias->findById($categoriaId) === null) {
            throw new ValidationException([
                'categoria_id' => ['La categoria indicada no existe.'],
            ]);
        }

        $search = $search !== null ? trim($search) : null;
        if ($search === '') {
            $search = null;
        }

        $total = max(0, $this->repository->countFiltered($categoriaId, $search));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page    = min($page, $totalPages);

        $offset  = ($page - 1) * $perPage;
        $items   = array_map(
            static fn (Item $item) => $item->toArray(),
            $this->repository->findPageFiltered($categoriaId, $search, $offset, $perPage),
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

    /** GET /items/{id} -> 404 si no existe. */
    public function getById(int $id): array
    {
        return $this->requireItem($id)->toArray();
    }

    /** POST /items -> valida, aplica reglas y crea. */
    public function create(array $data): array
    {
        [$nombre, $precio, $categoriaId] = $this->validate($data);
        $this->assertNameAvailable($nombre);

        return $this->repository->create(new Item(null, $nombre, $precio, $categoriaId))->toArray();
    }

    /** PUT /items/{id} -> 404 si no existe; valida; respeta regla de nombre único. */
    public function update(int $id, array $data): array
    {
        $this->requireItem($id);
        [$nombre, $precio, $categoriaId] = $this->validate($data);

        $other = $this->repository->findByName($nombre);
        if ($other !== null && $other->getId() !== $id) {
            throw new ValidationException([
                'nombre' => ["Ya existe otro item con el nombre '{$nombre}'."],
            ]);
        }

        return $this->repository->update(new Item($id, $nombre, $precio, $categoriaId))->toArray();
    }

    /** DELETE /items/{id} -> 404 si no existía. */
    public function delete(int $id): void
    {
        if (!$this->repository->delete($id)) {
            throw new NotFoundException("No existe el item con id {$id}.");
        }
    }

    // ------------------------------------------------------------------
    // Validadores: parte de la lógica de negocio del servicio.
    // Acumulan TODOS los errores antes de fallar para devolver una
    // respuesta 422 completa en una sola pasada.
    // ------------------------------------------------------------------

    private function requireItem(int $id): Item
    {
        $item = $this->repository->findById($id);
        if ($item === null) {
            throw new NotFoundException("No existe el item con id {$id}.");
        }

        return $item;
    }

    private function assertNameAvailable(string $nombre): void
    {
        if ($this->repository->findByName($nombre) !== null) {
            throw new ValidationException([
                'nombre' => ["Ya existe un item con el nombre '{$nombre}'."],
            ]);
        }
    }

    /**
     * Valida "nombre", "precio" y "categoria_id"; devuelve los valores
     * normalizados. CAVEAT de diseño: la validación de la FK se apoya en el
     * repositorio de categorias (consulta extra por cada create/update) para
     * que el error sea 422 y no un constraint SQL.
     *
     * Reglas:
     *  - nombre: obligatorio, texto recortado, entre 1 y 100 caracteres.
     *  - precio: obligatorio, numérico, mayor o igual que cero.
     *  - categoria_id: OPCIONAL; si viene, entero positivo y debe existir.
     *
     * @return array{0: string, 1: float, 2: ?int} [nombre, precio, categoria_id]
     */
    private function validate(array $data): array
    {
        $errors = [];
        $categoriaId = null;

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

        if (!array_key_exists('precio', $data)) {
            $errors['precio'][] = 'El precio es obligatorio.';
        } elseif (!is_numeric($data['precio'])) {
            $errors['precio'][] = 'El precio debe ser un número.';
        } elseif ((float) $data['precio'] < 0) {
            $errors['precio'][] = 'El precio no puede ser negativo.';
        }

        if (array_key_exists('categoria_id', $data) && $data['categoria_id'] !== null) {
            $raw = $data['categoria_id'];
            $candidato = filter_var($raw, FILTER_VALIDATE_INT);
            if ($candidato === false || (int) $candidato < 1) {
                $errors['categoria_id'][] = 'La categoria debe ser un entero positivo o null.';
            } elseif ($this->categorias->findById((int) $candidato) === null) {
                $errors['categoria_id'][] = 'La categoria indicada no existe.';
            } else {
                $categoriaId = (int) $candidato;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [trim((string) $data['nombre']), (float) $data['precio'], $categoriaId];
    }
}