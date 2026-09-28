<?php

declare(strict_types=1);

namespace App\Models;

/*
 * Entidad del dominio: representa un "pedido" de un item.
 * No sabe nada sobre HTTP ni sobre bases de datos.
 *
 * Un pedido siempre referencia UN item (item_id), lleva la cantidad pedida,
 * el total ya calculado (precio * cantidad en el momento de la compra) y el
 * usuario que lo solicitó (username, tomado del JWT). La entidad no conoce el
 * Item completo, solo su id.
 */
final class Pedido
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $itemId,
        private readonly int $cantidad,
        private readonly float $total,
        private readonly string $username,
        private readonly string $createdAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getCantidad(): int
    {
        return $this->cantidad;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /** Representación como array lista para serializar a JSON. */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'item_id'    => $this->itemId,
            'cantidad'   => $this->cantidad,
            'total'      => $this->total,
            'username'   => $this->username,
            'created_at' => $this->createdAt,
        ];
    }
}