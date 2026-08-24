<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/*
 * Emisión y verificación de tokens JWT (JSON Web Token) con firma HS256
 * (HMAC-SHA256), implementado a mano con funciones nativas de PHP para que
 * la clase pueda ver cómo funciona por dentro (en producción conviene una
 * librería mantenida como firebase/php-jwt).
 *
 * Estructura: base64url(header) . "." . base64url(payload) . "." . firma
 *
 * - issue(): agrega claims iat/exp/iss y firma el token con el secreto.
 * - verify(): comprueba firma (hash_equals, tiempo constante), algoritmo del
 *   header y vigencia (claim exp). Devuelve los claims o null si algo falla.
 */
final class JwtService
{
    private const ALG = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 3600,
        private readonly string $issuer = 'api-items',
    ) {
        if ($this->secret === '') {
            throw new RuntimeException('JWT_SECRET no puede estar vacío.');
        }
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    /**
     * Emite un token firmado. $claims son los datos "de negocio"
     * (p. ej. sub, username) que viajarán visibles dentro del payload.
     *
     * @param array<string, mixed> $claims
     */
    public function issue(array $claims): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], $claims);

        $header = $this->base64UrlEncode(
            (string) json_encode(['alg' => self::ALG, 'typ' => 'JWT'], JSON_UNESCAPED_UNICODE)
        );
        $body = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $header . '.' . $body . '.' . $this->signature($header . '.' . $body);
    }

    /**
     * Verifica firma, algoritmo y expiración.
     *
     * @return array<string, mixed>|null los claims si el token es válido; null en caso contrario.
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $signature] = $parts;

        // 1) Firma: comparación en tiempo constante para evitar timing attacks.
        if (!hash_equals($signature, $this->signature($header . '.' . $body))) {
            return null;
        }

        // 2) Header: el algoritmo declarado debe ser el que nosotros usamos.
        $rawHeader = $this->base64UrlDecode($header);
        if ($rawHeader === false) {
            return null;
        }
        $headerData = json_decode($rawHeader, true);
        if (!is_array($headerData) || ($headerData['alg'] ?? '') !== self::ALG) {
            return null;
        }

        // 3) Payload decodificable...
        $rawBody = $this->base64UrlDecode($body);
        if ($rawBody === false) {
            return null;
        }
        $claims = json_decode($rawBody, true);
        if (!is_array($claims)) {
            return null;
        }

        // 4) ...y vigente (exp en segundos Unix).
        if (!isset($claims['exp']) || !is_int($claims['exp']) || $claims['exp'] < time()) {
            return null;
        }

        return $claims;
    }

    /** HMAC-SHA256 codificado en base64url: la firma del token. */
    private function signature(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    /** Base64 variante URL-safe (sin +, / ni =), tal como exige el estándar JWT. */
    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
