<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Item;
use App\Models\User;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testItemGuardaSusPropiedades(): void
    {
        $item = new Item(5, 'Teclado', 25.5);

        $this->assertSame(5, $item->getId());
        $this->assertSame('Teclado', $item->getNombre());
        $this->assertSame(25.5, $item->getPrecio());
    }

    public function testItemNuevoTieneIdNulo(): void
    {
        $item = new Item(null, 'Nuevo', 1.0);

        $this->assertNull($item->getId());
    }

    public function testItemToArraySerializaLosTresCampos(): void
    {
        $item = new Item(2, 'Mouse', 15.9);

        $this->assertSame(['id' => 2, 'nombre' => 'Mouse', 'precio' => 15.9], $item->toArray());
    }

    public function testUserToArrayNuncaExponeElHash(): void
    {
        $user = new User(1, 'admin', '$2y$12$hash.falso.de.prueba');

        $this->assertSame(1, $user->getId());
        $this->assertSame('admin', $user->getUsername());
        $this->assertSame(['id' => 1, 'username' => 'admin'], $user->toArray());
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
    }
}