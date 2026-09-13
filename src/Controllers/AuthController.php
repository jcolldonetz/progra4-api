<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Services\AuthService;
use App\Security\JwtService;

/*
 * Controlador de AUTENTICACIÓN: traduce entre HTTP y el servicio de auth.
 *
 * - Emite token vía JSON (Bearer) como siempre.
 * - Opcionalmente emite una cookie HttpOnly (auth_cookie) para demostrar que
 *   las cookies viajan solas con cada petición y no son legibles por JS.
 * - Endpoints protegidos: /me (requiere token en Bearer o en cookie).
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly JwtService $jwt,
        private readonly int $ttlSeconds,
    ) {
    }

    /** POST /register -> 201 con JWT | 422 validación | 422 usuario duplicado. */
    public function register(array $body): JsonResponse
    {
        $response = new JsonResponse(201, $this->authService->register($body));

        if (!empty($body['auth_cookie'])) {
            $this->emitTokenCookie($response->data['token']);
        }

        return $response;
    }

    /** POST /login -> 200 con JWT | 401 credenciales inválidas | 422 campos faltantes. */
    public function login(array $body): JsonResponse
    {
        $response = new JsonResponse(200, $this->authService->login($body));

        if (!empty($body['auth_cookie'])) {
            $this->emitTokenCookie($response->data['token']);
        }

        return $response;
    }

    /**
     * GET /me -> 200 con datos del usuario desde el token (Bearer o cookie).
     * Útil para que la SPA restaure la sesión HttpOnly al recargar (la cookie
     * existe pero JS no puede leerla para restaurar el estado local).
     */
    public function me(array $claims): JsonResponse
    {
        return new JsonResponse(200, [
            'user' => ['id' => $claims['sub'], 'username' => $claims['username']],
        ]);
    }

    /**
     * Emite el JWT como cookie HttpOnly.
     * El navegador la envía automáticamente en cada petición a la API,
     * pero JavaScript no puede leerla (la brecha de seguridad que se
     * ilustra en clase).
     */
    private function emitTokenCookie(string $token): void
    {
        header('Set-Cookie: access_token='
            . rawurlencode($token)
            . '; Path=/; HttpOnly; SameSite=Lax'
            . '; Max-Age=' . $this->ttlSeconds);
    }
}
