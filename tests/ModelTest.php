<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Categoria;
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
        $this->assertNull($item->getCategoriaId());
    }

    public function testItemNuevoTieneIdNulo(): void
    {
        $item = new Item(null, 'Nuevo', 1.0);

        $this->assertNull($item->getId());
    }

    public function testItemGuardaCategoriaId(): void
    {
        $item = new Item(3, 'Monitor', 189.99, 7);

        $this->assertSame(7, $item->getCategoriaId());
    }

    public function testItemToArraySerializaTodosLosCampos(): void
    {
        $item = new Item(2, 'Mouse', 15.9, 3);

        $this->assertSame(
            ['id' => 2, 'nombre' => 'Mouse', 'precio' => 15.9, 'categoria_id' => 3],
            $item->toArray()
        );
    }

    public function testCategoriaNuevaTieneIdNulo(): void
    {
        $categoria = new Categoria(null, 'Periféricos');

        $this->assertNull($categoria->getId());
        $this->assertSame('Periféricos', $categoria->getNombre());
        $this->assertSame(['id' => null, 'nombre' => 'Periféricos'], $categoria->toArray());
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