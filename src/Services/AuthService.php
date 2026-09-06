<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Repositories\UserRepositoryInterface;
use App\Security\JwtService;

/*
 * Servicio de AUTENTICACIÓN: caso de uso "iniciar sesión".
 *
 * - Valida que lleguen usuario y contraseña (422 si faltan).
 * - Busca el usuario en la BD vía su interface de repositorio.
 * - Compara la contraseña contra el HASH bcrypt guardado con password_verify()
 *   (nunca hay texto plano en la base de datos).
 * - Si todo coincide, emite un JWT firmado que el cliente enviará como
 *   Bearer token en las siguientes peticiones.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly JwtService $jwt,
    ) {
    }

    /**
     * Registra un nuevo usuario y devuelve el JWT.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: array}
     */
    public function register(array $data): array
    {
        $errors = [];

        $username = isset($data['username']) && is_string($data['username']) ? trim($data['username']) : '';
        if ($username === '') {
            $errors['username'][] = 'El usuario es obligatorio.';
        } elseif (strlen($username) < 3) {
            $errors['username'][] = 'El usuario debe tener al menos 3 caracteres.';
        } elseif (strlen($username) > 50) {
            $errors['username'][] = 'El usuario no puede superar los 50 caracteres.';
        }

        if (!isset($data['password']) || !is_string($data['password']) || $data['password'] === '') {
            $errors['password'][] = 'La contraseña es obligatoria.';
        } elseif (strlen($data['password']) < 4) {
            $errors['password'][] = 'La contraseña debe tener al menos 4 caracteres.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new ValidationException(['username' => ['El usuario ya está registrado.']]);
        }

        $id = $this->users->save($username, password_hash($data['password'], PASSWORD_BCRYPT));

        return [
            'token'      => $this->jwt->issue([
                'sub'      => $id,
                'username' => $username,
            ]),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttlSeconds(),
            'user'       => ['id' => $id, 'username' => $username],
        ];
    }

    /**
     * @return array{token: string, token_type: string, expires_in: int, user: array}
     */
    public function login(array $data): array
    {
        $errors = [];

        $username = isset($data['username']) && is_string($data['username']) ? trim($data['username']) : '';
        if ($username === '') {
            $errors['username'][] = 'El usuario es obligatorio.';
        }

        if (!isset($data['password']) || !is_string($data['password']) || $data['password'] === '') {
            $errors['password'][] = 'La contraseña es obligatoria.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->users->findByUsername($username);

        // Mensaje genérico a propósito: no se revela si falló el usuario o la clave.
        if ($user === null || !password_verify($data['password'], $user->getPasswordHash())) {
            throw new UnauthorizedException('Credenciales inválidas.');
        }

        return [
            'token'      => $this->jwt->issue([
                'sub'      => $user->getId(),
                'username' => $user->getUsername(),
            ]),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttlSeconds(),
            'user'       => $user->toArray(),
        ];
    }
}
