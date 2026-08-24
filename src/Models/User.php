<?php

declare(strict_types=1);

namespace App\Models;

/*
 * Entidad del dominio: usuario con credenciales.
 * El hash de la contraseña nunca se expone en respuestas HTTP
 * (toArray() lo omite a propósito).
 */
final class User
{
    public function __construct(
        private readonly int $id,
        private readonly string $username,
        private readonly string $passwordHash,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /** Hash bcrypt almacenado en la BD; usar solo para password_verify(). */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /** Representación segura para JSON: sin hash. */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'username' => $this->username,
        ];
    }
}
