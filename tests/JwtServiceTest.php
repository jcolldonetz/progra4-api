<?php

declare(strict_types=1);

namespace App\Tests;

use App\Security\JwtService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JwtServiceTest extends TestCase
{
    private const SECRET = 'secreto-de-prueba';

    private function jwt(int $ttl = 3600): JwtService
    {
        return new JwtService(self::SECRET, $ttl);
    }

    public function testIssueDevuelveTokenDeTresPartes(): void
    {
        $token = $this->jwt()->issue(['sub' => 1, 'username' => 'admin']);

        $this->assertCount(3, explode('.', $token));
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
    }

    public function testVerifyDevuelveClaimsDeUnTokenValido(): void
    {
        $claims = $this->jwt()->verify(
            $this->jwt()->issue(['sub' => 42, 'username' => 'alice'])
        );

        $this->assertIsArray($claims);
        $this->assertSame(42, $claims['sub']);
        $this->assertSame('alice', $claims['username']);
        $this->assertSame('api-items', $claims['iss']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertSame(time() + 3600, $claims['exp']);
    }

    public function testTtlConfigurable(): void
    {
        $claims = $this->jwt(120)->verify($this->jwt(120)->issue([]));

        $this->assertSame(time() + 120, $claims['exp']);
        $this->assertSame(120, $this->jwt(120)->ttlSeconds());
    }

    public function testVerifyRechazaFirmaAlterada(): void
    {
        $token = $this->jwt()->issue(['sub' => 1]);
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');

        $this->assertNull($this->jwt()->verify($tampered));
    }

    public function testVerifyRechazaSecretoDistinto(): void
    {
        $token = $this->jwt()->issue(['sub' => 1]);

        $this->assertNull((new JwtService('otro-secreto'))->verify($token));
    }

    public function testVerifyRechazaAlgoritmoNoPermitido(): void
    {
        $now = time();
        $token = $this->buildRawToken(
            ['sub' => 1, 'exp' => $now + 3600],
            self::SECRET,
            ['alg' => 'none', 'typ' => 'JWT'],
        );

        $this->assertNull($this->jwt()->verify($token));
    }

    public function testVerifyRechazaTokenExpirado(): void
    {
        $token = $this->buildRawToken(
            ['sub' => 1, 'exp' => time() - 10],
            self::SECRET,
        );

        $this->assertNull($this->jwt()->verify($token));
    }

    public function testVerifyRechazaTokenSinExp(): void
    {
        $token = $this->buildRawToken(['sub' => 1], self::SECRET);

        $this->assertNull($this->jwt()->verify($token));
    }

    public function testVerifyRechazaFormatosInvalidos(): void
    {
        $jwt = $this->jwt();
        $this->assertNull($jwt->verify(''));
        $this->assertNull($jwt->verify('solo-una-parte'));
        $this->assertNull($jwt->verify('a.b.c.d'));
        $this->assertNull($jwt->verify('@@.@@.@@'));
    }

    public function testSecretoVacioLanzaRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET no puede estar vacío.');

        new JwtService('');
    }

    /**
     * Construye un token firmado de forma manual (misma lógica HS256 que
     * JwtService) para poder probar casos límite: expirado, sin exp, alg raro.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $header
     */
    private function buildRawToken(array $payload, string $secret, array $header = ['alg' => 'HS256', 'typ' => 'JWT']): string
    {
        $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $encodedHeader = $encode((string) json_encode($header, JSON_UNESCAPED_UNICODE));
        $encodedBody   = $encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature     = $encode(hash_hmac('sha256', $encodedHeader . '.' . $encodedBody, $secret, true));

        return $encodedHeader . '.' . $encodedBody . '.' . $signature;
    }
}