<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Services\PedidoService;

/*
 * Capa de CONTROLADOR del caso de uso "pedidos": traduce entre HTTP y dominio.
 *
 * - POST /pedidos crea un pedido (descuenta stock) y devuelve 201 con el
 *   pedido + el item con su nuevo stock.
 * - GET /pedidos devuelve el historial.
 * - El username sale de los claims del JWT y se inyecta desde el front
 *   controller; el controlador no conoce tokens.
 */
final class PedidoController
{
    public function __construct(private readonly PedidoService $service)
    {
    }

    /** GET /pedidos -> 200 con el historial de pedidos. */
    public function index(): JsonResponse
    {
        return new JsonResponse(200, $this->service->list());
    }

    /** POST /pedidos -> 201 | 404 (item inexistente) | 422 (stock insuficiente). */
    public function store(string $username, array $body): JsonResponse
    {
        return new JsonResponse(201, $this->service->place($username, $body));
    }
}