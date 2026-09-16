<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Http\JsonResponse;
use App\Services\CategoriaService;

/*
 * Capa de CONTROLADOR de categorias: traduce entre HTTP y el dominio.
 * No conoce repositorios: solo delega en CategoriaService.
 */
final class CategoriaController
{
    public function __construct(private readonly CategoriaService $service)
    {
    }

    /** GET /categorias -> 200 con la lista (incluye items_count). */
    public function index(): JsonResponse
    {
        return new JsonResponse(200, $this->service->listAll());
    }

    /** GET /categorias/{id} -> 200 | 404. */
    public function show(string $id): JsonResponse
    {
        return new JsonResponse(200, $this->service->getById($this->parseId($id)));
    }

    /** GET /categorias/{id}/items?page=&per_page= -> 200 con { data, meta }. */
    public function items(string $id, int $page = 1, int $perPage = 10): JsonResponse
    {
        return new JsonResponse(200, $this->service->listItemsPaginated($this->parseId($id), $page, $perPage));
    }

    /** POST /categorias -> 201 con la categoria creada. */
    public function store(array $body): JsonResponse
    {
        return new JsonResponse(201, $this->service->create($body));
    }

    /** PUT /categorias/{id} -> 200 | 404 | 422. */
    public function update(string $id, array $body): JsonResponse
    {
        return new JsonResponse(200, $this->service->update($this->parseId($id), $body));
    }

    /** DELETE /categorias/{id} -> 204 sin cuerpo | 404 | 422. */
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