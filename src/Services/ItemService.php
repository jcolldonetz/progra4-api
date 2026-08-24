<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Item;
use App\Repositories\ItemRepositoryInterface;

/*
 * Capa de SERVICIO: concentra la LÓGICA DE NEGOCIO del caso de uso "items".
 *
 * - Depende de la INTERFACE ItemRepositoryInterface, no de una implementación
 *   concreta (inversión de dependencias).
 * - Aquí viven los VALIDADORES y las reglas de negocio (p. ej. nombre único,
 *   precio no negativo). El repositorio solo guarda/recupera datos.
 */
final class ItemService
{
    public function __construct(private readonly ItemRepositoryInterface $repository)
    {
    }

    /** GET /items -> lista completa. */
    public function listAll(): array
    {
        return array_map(static fn (Item $item) => $item->toArray(), $this->repository->findAll());
    }

    /** GET /items/{id} -> 404 si no existe. */
    public function getById(int $id): array
    {
        return $this->requireItem($id)->toArray();
    }

    /** POST /items -> valida, aplica reglas y crea. */
    public function create(array $data): array
    {
        [$nombre, $precio] = $this->validate($data);
        $this->assertNameAvailable($nombre);

        return $this->repository->create(new Item(null, $nombre, $precio))->toArray();
    }

    /** PUT /items/{id} -> 404 si no existe; valida; respeta regla de nombre único. */
    public function update(int $id, array $data): array
    {
        $this->requireItem($id);
        [$nombre, $precio] = $this->validate($data);

        $other = $this->repository->findByName($nombre);
        if ($other !== null && $other->getId() !== $id) {
            throw new ValidationException([
                'nombre' => ["Ya existe otro item con el nombre '{$nombre}'."],
            ]);
        }

        return $this->repository->update(new Item($id, $nombre, $precio))->toArray();
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
     * Valida "nombre" y "precio"; devuelve los valores normalizados.
     *
     * Reglas:
     *  - nombre: obligatorio, texto recortado, entre 1 y 100 caracteres.
     *  - precio: obligatorio, numérico, mayor o igual que cero.
     *
     * @return array{0: string, 1: float} [nombre, precio]
     */
    private function validate(array $data): array
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

        if (!array_key_exists('precio', $data)) {
            $errors['precio'][] = 'El precio es obligatorio.';
        } elseif (!is_numeric($data['precio'])) {
            $errors['precio'][] = 'El precio debe ser un número.';
        } elseif ((float) $data['precio'] < 0) {
            $errors['precio'][] = 'El precio no puede ser negativo.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [trim((string) $data['nombre']), (float) $data['precio']];
    }
}
