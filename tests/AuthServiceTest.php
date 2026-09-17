<?php

declare(strict_types=1);

namespace App\Tests;

use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Repositories\InMemoryUserRepository;
use App\Security\JwtService;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        // InMemoryUserRepository siembra al usuario admin/qwerty67 en cada instancia.
        $this->service = new AuthService(new InMemoryUserRepository(), new JwtService('secreto-test'));
    }

    public function testLoginValidoDevuelveTokenYUsuario(): void
    {
        $result = $this->service->login(['username' => 'admin', 'password' => 'qwerty67']);

        $this->assertArrayHasKey('token', $result);
        $this->assertIsString($result['token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame(3600, $result['expires_in']);
        $this->assertSame(['id' => 1, 'username' => 'admin'], $result['user']);
        $this->assertArrayNotHasKey('password_hash', $result['user']);
    }

    public function testLoginConContrasenaIncorrectaLanza401(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Credenciales inválidas.');

        $this->service->login(['username' => 'admin', 'password' => 'incorrecta']);
    }

    public function testLoginConUsuarioInexistenteLanza401ConMismoMensaje(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Credenciales inválidas.');

        $this->service->login(['username' => 'ghost', 'password' => 'qwerty67']);
    }

    public function testLoginSinUsernameLanza422(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->login(['password' => 'qwerty67']);
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->httpStatus());
            $this->assertArrayHasKey('username', $e->getErrors());
            $this->assertSame(['El usuario es obligatorio.'], $e->getErrors()['username']);
            throw $e;
        }
    }

    public function testLoginSinPasswordLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->login(['username' => 'admin']);
    }

    public function testRegisterCreaUsuarioYPermiteLoguearse(): void
    {
        $result = $this->service->register(['username' => 'nuevo', 'password' => 'abcd']);

        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame('nuevo', $result['user']['username']);
        $this->assertArrayNotHasKey('password_hash', $result['user']);

        $login = $this->service->login(['username' => 'nuevo', 'password' => 'abcd']);
        $this->assertSame('nuevo', $login['user']['username']);
    }

    public function testRegisterDuplicadoLanza422(): void
    {
        $this->service->register(['username' => 'ana', 'password' => 'abcd']);

        $this->expectException(ValidationException::class);

        try {
            $this->service->register(['username' => 'ana', 'password' => 'abcd']);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->getErrors());
            $this->assertSame(['El usuario ya está registrado.'], $e->getErrors()['username']);
            throw $e;
        }
    }

    public function testRegisterUsernameCortoLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->register(['username' => 'ab', 'password' => 'abcd']);
    }

    public function testRegisterPasswordCortoOLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->register(['username' => 'vic', 'password' => 'abc']);
    }

    public function testRegisterAcumulaErroresDeTodosLosCampos(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->register(['username' => '', 'password' => '']);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('username', $errors);
            $this->assertArrayHasKey('password', $errors);
            $this->assertNotSame([], $errors['username']);
            $this->assertNotSame([], $errors['password']);
            throw $e;
        }
    }
}