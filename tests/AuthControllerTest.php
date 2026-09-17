<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\AuthController;
use App\Repositories\InMemoryUserRepository;
use App\Security\JwtService;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private AuthController $controller;

    protected function setUp(): void
    {
        $service = new AuthService(new InMemoryUserRepository(), new JwtService('secreto-test'));
        $this->controller = new AuthController($service);
    }

    public function testLoginDevuelve200ConToken(): void
    {
        $response = $this->controller->login(['username' => 'admin', 'password' => 'qwerty67']);

        $this->assertSame(200, $response->status);
        $this->assertIsString($response->data['token']);
        $this->assertSame('admin', $response->data['user']['username']);
        $this->assertArrayNotHasKey('password_hash', $response->data['user']);
    }

    public function testRegisterDevuelve201(): void
    {
        $response = $this->controller->register([
            'username' => 'tester',
            'password' => 'clave',
        ]);

        $this->assertSame(201, $response->status);
        $this->assertSame('tester', $response->data['user']['username']);
    }
}