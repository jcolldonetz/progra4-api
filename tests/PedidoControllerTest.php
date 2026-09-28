<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\PedidoController;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Realtime\NullEventPublisher;
use App\Repositories\InMemoryItemRepository;
use App\Repositories\InMemoryPedidoRepository;
use App\Services\PedidoService;
use PHPUnit\Framework\TestCase;

final class PedidoControllerTest extends TestCase
{
    private PedidoController $controller;

    protected function setUp(): void
    {
        $items = new InMemoryItemRepository();
        $this->controller = new PedidoController(
            new PedidoService(new InMemoryPedidoRepository($items), $items, new NullEventPublisher()),
        );
    }

    public function testStoreDevuelve201ConPedidoYItemActualizado(): void
    {
        $response = $this->controller->store('admin', ['item_id' => 1, 'cantidad' => 1]);

        $this->assertSame(201, $response->status);
        $this->assertSame(1, $response->data['pedido']['id']);
        $this->assertSame(25.5, $response->data['pedido']['total']);
        $this->assertSame(11, $response->data['item']['stock']);
        $this->assertSame('admin', $response->data['username']);
    }

    public function testStoreConStockInsuficienteLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->store('admin', ['item_id' => 3, 'cantidad' => 99]);
    }

    public function testStoreConItemInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->store('admin', ['item_id' => 999, 'cantidad' => 1]);
    }

    public function testIndexDevuelve200ConHistorial(): void
    {
        $this->controller->store('admin', ['item_id' => 1, 'cantidad' => 1]);

        $response = $this->controller->index();

        $this->assertSame(200, $response->status);
        $this->assertCount(1, $response->data);
        $this->assertSame(1, $response->data[0]['item_id']);
    }
}