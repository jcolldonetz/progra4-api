<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Categoria;
use App\Models\Item;
use App\Models\Pedido;
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
        $this->assertSame(0, $item->getStock());
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
        $this->assertSame(0, $item->getStock());
    }

    public function testItemGuardaStock(): void
    {
        $item = new Item(4, 'Impresora', 120.0, null, 15);

        $this->assertSame(15, $item->getStock());
    }

    public function testItemToArraySerializaTodosLosCampos(): void
    {
        $item = new Item(2, 'Mouse', 15.9, 3, 25);

        $this->assertSame(
            ['id' => 2, 'nombre' => 'Mouse', 'precio' => 15.9, 'categoria_id' => 3, 'stock' => 25],
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

    public function testPedidoGuardaSusPropiedades(): void
    {
        $pedido = new Pedido(3, 1, 2, 51.0, 'admin', '2026-09-28T14:30:00+00:00');

        $this->assertSame(3, $pedido->getId());
        $this->assertSame(1, $pedido->getItemId());
        $this->assertSame(2, $pedido->getCantidad());
        $this->assertSame(51.0, $pedido->getTotal());
        $this->assertSame('admin', $pedido->getUsername());
        $this->assertSame('2026-09-28T14:30:00+00:00', $pedido->getCreatedAt());
    }

    public function testPedidoNuevoTieneIdNulo(): void
    {
        $pedido = new Pedido(null, 1, 1, 25.5, 'ana', '2026-09-28T14:31:00+00:00');

        $this->assertNull($pedido->getId());
    }

    public function testPedidoToArraySerializaTodosLosCampos(): void
    {
        $pedido = new Pedido(1, 2, 3, 47.7, 'admin', '2026-09-28T14:30:00+00:00');

        $this->assertSame(
            [
                'id'         => 1,
                'item_id'    => 2,
                'cantidad'   => 3,
                'total'      => 47.7,
                'username'   => 'admin',
                'created_at' => '2026-09-28T14:30:00+00:00',
            ],
            $pedido->toArray(),
        );
    }
}