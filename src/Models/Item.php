<?php

declare(strict_types=1);

namespace App\Models;

/*
 * Entidad del dominio: representa un "item" con sus datos.
 * No sabe nada sobre HTTP ni sobre bases de datos.
 *
 * Relación: un item pertenece a UNA categoria (1:N); categoriaId es opcional
 * (null = sin categoría). La entidad no conoce la Categoria completa, solo su id.
 */
final class Item
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly float $precio,
        private readonly ?int $categoriaId = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getPrecio(): float
    {
        return $this->precio;
    }

    public function getCategoriaId(): ?int
    {
        return $this->categoriaId;
    }

    /** Representación como array lista para serializar a JSON. */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'nombre'       => $this->nombre,
            'precio'       => $this->precio,
            'categoria_id' => $this->categoriaId,
        ];
    }
}
