<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Http\JsonResponse;
use App\Services\ItemService;

/*
 * Capa de CONTROLADOR: traduce entre HTTP y el dominio.
 *
 * - Recibe la petición (id, cuerpo JSON) y llama al servicio.
 * - Decide el código HTTP de la respuesta (200, 201, 204).
 * - NO conoce PDO ni detalles de almacenamiento: solo habla con ItemService.
 */
final class ItemController
{
    public function __construct(private readonly ItemService $service)
    {
    }

    /** GET /items -> 200 con la lista. */
    public function index(): JsonResponse
    {
        return new JsonResponse(200, $this->service->listAll());
    }

    /** GET /items/{id} -> 200 | 404. */
    public function show(string $id): JsonResponse
    {
        return new JsonResponse(200, $this->service->getById($this->parseId($id)));
    }

    /** POST /items -> 201 con el item creado. */
    public function store(array $body): JsonResponse
    {
        return new JsonResponse(201, $this->service->create($body));
    }

    /** PUT /items/{id} -> 200 | 404 | 422. */
    public function update(string $id, array $body): JsonResponse
    {
        return new JsonResponse(200, $this->service->update($this->parseId($id), $body));
    }

    /** DELETE /items/{id} -> 204 sin cuerpo | 404. */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($this->parseId($id));

        return new JsonResponse(204, null);
    }

    private function parseId(string $raw): int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new ValidationException(['id' => ['El identificador debe ser un entero positivo.']]);
        }

        return $id;
    }
}
