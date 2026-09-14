<?php

declare(strict_types=1);

namespace App\Models;

final class Categoria
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
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

    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'nombre' => $this->nombre,
        ];
    }
}