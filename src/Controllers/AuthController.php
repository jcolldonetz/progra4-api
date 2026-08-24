<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Services\AuthService;

/*
 * Controlador de AUTENTICACIÓN: traduce entre HTTP y el servicio de auth.
 * No conoce repositorios ni JWT: solo delega en AuthService.
 */
final class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /** POST /login -> 200 con JWT | 401 credenciales inválidas | 422 campos faltantes. */
    public function login(array $body): JsonResponse
    {
        return new JsonResponse(200, $this->authService->login($body));
    }
}
