<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Pedido;

/*
 * IMPLEMENTACIÓN VOLÁTIL: guarda los pedidos en un ARRAY en memoria RAM.
 * Los datos viven solo mientras dura el proceso. Comparte el almacén de items
 * con InMemoryItemRepository para poder descontar stock.
 */
final class InMemoryPedidoRepository implements PedidoRepositoryInterface
{
    /** @var Pedido[] */
    private array $pedidos = [];

    private int $nextId = 1;

    public function __construct(private readonly InMemoryItemRepository $items)
    {
    }

    public function placeOrder(int $itemId, int $cantidad, string $username): ?Pedido
    {
        $item = $this->items->findById($itemId);
        if ($item === null || $item->getStock() < $cantidad) {
            return null;
        }

        if (!$this->items->decrementStock($itemId, $cantidad)) {
            return null;
        }

        $pedido = new Pedido(
            $this->nextId,
            $itemId,
            $cantidad,
            $item->getPrecio() * $cantidad,
            $username,
            gmdate('c'),
        );
        $this->nextId++;
        $this->pedidos[] = $pedido;

        return $pedido;
    }

    public function findAll(): array
    {
        return $this->pedidos;
    }
}