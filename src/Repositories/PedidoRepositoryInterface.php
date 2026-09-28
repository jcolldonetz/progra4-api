<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Pedido;

/*
 * CONTRATO de acceso a datos para la entidad Pedido.
 *
 * El caso de uso central es `placeOrder`: registrar un pedido DESCONTANDO el
 * stock del item en una MISMA operación atómica. Si el item no existe o su
 * stock actual es insuficiente, no se descuenta nada y se devuelve null.
 *
 * Cada implementación garantiza la atomicidad a su manera:
 *   - SqlitePedidoRepository: transacción PDO (UPDATE items guardado + INSERT
 *     pedidos) sobre la misma conexión a la base.
 *   - InMemoryPedidoRepository: chequeo + decremento + inserción en memoria
 *     RAM (sin transacción real, secuencial por ser un solo proceso).
 */
interface PedidoRepositoryInterface
{
    /**
     * Registra un pedido descontando el stock del item de forma atómica.
     *
     * @return ?Pedido el pedido creado, o null si el item no existe o el
     *                stock actual no alcanza para la cantidad pedida.
     */
    public function placeOrder(int $itemId, int $cantidad, string $username): ?Pedido;

    /** @return Pedido[] todos los pedidos en orden de creación. */
    public function findAll(): array;
}