<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Pedido;
use App\Realtime\EventPublisherInterface;
use App\Repositories\ItemRepositoryInterface;
use App\Repositories\PedidoRepositoryInterface;

/*
 * Capa de SERVICIO del caso de uso "pedidos".
 *
 * Responsabilidades:
 *   - Validar el cuerpo de la petición (item_id y cantidad).
 *   - Delegar el descuento atómico de stock + alta del pedido al repositorio
 *     (placeOrder). Si devuelve null, el stock no alcanzó -> 422.
 *   - PUBLICAR el evento realtime (canal "items.stock") para que los demás
 *     clientes conectados actualicen el stock en vivo. La publicación es
 *     "best-effort": cualquier fallo se registra y NO rompe la respuesta HTTP.
 */
final class PedidoService
{
    public const STOCK_CHANNEL = 'items.stock';

    public function __construct(
        private readonly PedidoRepositoryInterface $repository,
        private readonly ItemRepositoryInterface $items,
        private readonly EventPublisherInterface $publisher,
    ) {
    }

    /**
     * POST /pedidos -> valida, descuenta stock y crea el pedido.
     *
     * @return array{"pedido": array, "item": array, "username": string}
     */
    public function place(string $username, array $data): array
    {
        [$itemId, $cantidad] = $this->validate($data);

        $item = $this->items->findById($itemId);
        if ($item === null) {
            throw new NotFoundException("No existe el item con id {$itemId}.");
        }

        $pedido = $this->repository->placeOrder($itemId, $cantidad, $username);
        if ($pedido === null) {
            throw new ValidationException([
                'stock' => ["Stock insuficiente para '{$item->getNombre()}'. Disponible: {$item->getStock()}."],
            ]);
        }

        // La entrada del item ya refleja el descuento (misma operación atómica).
        /** @var \App\Models\Item $updated */
        $updated = $this->items->findById($itemId);

        $this->publishEvent($username, $pedido, $updated->toArray());

        return [
            'pedido'   => $pedido->toArray(),
            'item'     => $updated->toArray(),
            'username' => $username,
        ];
    }

    /** GET /pedidos -> historial completo de pedidos. */
    public function list(): array
    {
        return array_map(
            static fn (Pedido $pedido): array => $pedido->toArray(),
            $this->repository->findAll(),
        );
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /**
     * @return array{0: int, 1: int} [item_id, cantidad] normalizados.
     */
    private function validate(array $data): array
    {
        $errors = [];

        $itemId = null;
        if (!array_key_exists('item_id', $data)) {
            $errors['item_id'][] = 'El item_id es obligatorio.';
        } elseif (!is_int($data['item_id'])) {
            $errors['item_id'][] = 'El item_id debe ser un entero positivo.';
        } else {
            $itemId = $data['item_id'];
            if ($itemId < 1) {
                $errors['item_id'][] = 'El item_id debe ser un entero positivo.';
            }
        }

        $cantidad = null;
        if (!array_key_exists('cantidad', $data)) {
            $errors['cantidad'][] = 'La cantidad es obligatoria.';
        } elseif (!is_int($data['cantidad'])) {
            $errors['cantidad'][] = 'La cantidad debe ser un entero positivo.';
        } else {
            $cantidad = $data['cantidad'];
            if ($cantidad < 1) {
                $errors['cantidad'][] = 'La cantidad debe ser un entero positivo.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$itemId, $cantidad];
    }

    /** Publica el evento realtime; cualquier fallo se registra y se ignora. */
    private function publishEvent(string $username, Pedido $pedido, array $item): void
    {
        $payload = [
            'type'     => 'pedido.creado',
            'pedido'   => $pedido->toArray(),
            'item'     => $item,
            'username' => $username,
        ];

        try {
            $this->publisher->publish(self::STOCK_CHANNEL, $payload);
        } catch (\Throwable $e) {
            error_log('[realtime] No se pudo publicar el evento: ' . $e->getMessage());
        }
    }
}