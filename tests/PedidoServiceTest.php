<?php

declare(strict_types=1);

namespace App\Tests;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Realtime\EventPublisherInterface;
use App\Services\PedidoService;
use App\Repositories\InMemoryItemRepository;
use App\Repositories\InMemoryPedidoRepository;
use PHPUnit\Framework\TestCase;

final class PedidoServiceTest extends TestCase
{
    private InMemoryItemRepository $items;
    private InMemoryPedidoRepository $pedidos;
    private PedidoService $service;

    /** @var array<int, array{0: string, 1: array}> */
    private array $published = [];

    protected function setUp(): void
    {
        $this->items    = new InMemoryItemRepository();
        $this->pedidos  = new InMemoryPedidoRepository($this->items);
        $this->published = [];

        $publisher = new class ($this->published) implements EventPublisherInterface {
            /** @var array<int, array{0: string, 1: array}> */
            private array $published;

            public function __construct(array &$published)
            {
                $this->published = &$published;
            }

            public function publish(string $channel, array $payload): void
            {
                $this->published[] = [$channel, $payload];
            }
        };

        $this->service = new PedidoService($this->pedidos, $this->items, $publisher);
    }

    public function testPlaceDescuentaStockYDevuelvePedido(): void
    {
        $result = $this->service->place('admin', ['item_id' => 1, 'cantidad' => 2]);

        $this->assertSame(25.5 * 2, $result['pedido']['total']);
        $this->assertSame(1, $result['pedido']['item_id']);
        $this->assertSame(2, $result['pedido']['cantidad']);
        $this->assertSame('admin', $result['pedido']['username']);
        $this->assertSame(10, $result['item']['stock']);
        $this->assertSame(10, $this->items->findById(1)?->getStock());
    }

    public function testPlaceConStockInsuficienteLanza422YNoModificaNada(): void
    {
        try {
            $this->service->place('admin', ['item_id' => 3, 'cantidad' => 6]);
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->getErrors());
        }

        $this->assertSame(5, $this->items->findById(3)?->getStock());
        $this->assertSame([], $this->pedidos->findAll());
    }

    public function testPlaceConItemInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->place('admin', ['item_id' => 999, 'cantidad' => 1]);
    }

    public function testPlaceConDatosInvalidosLanza422(): void
    {
        $this->expectException(ValidationException::class);

        foreach ([
            ['cantidad' => 1],
            ['item_id' => '1', 'cantidad' => 1],
            ['item_id' => 1, 'cantidad' => 0],
        ] as $data) {
            $this->service->place('admin', $data);
        }
    }

    public function testPlacePublicaEventoRealtime(): void
    {
        $this->service->place('ana', ['item_id' => 2, 'cantidad' => 3]);

        $this->assertCount(1, $this->published);
        [$channel, $payload] = $this->published[0];

        $this->assertSame(PedidoService::STOCK_CHANNEL, $channel);
        $this->assertSame('pedido.creado', $payload['type']);
        $this->assertSame(2, $payload['item']['id']);
        $this->assertSame(30 - 3, $payload['item']['stock']);
        $this->assertSame('ana', $payload['username']);
        $this->assertSame(15.9 * 3, $payload['pedido']['total']);
    }

    public function testListDevuelveHistorialOrdenado(): void
    {
        $this->service->place('admin', ['item_id' => 1, 'cantidad' => 1]);
        $this->service->place('ana', ['item_id' => 2, 'cantidad' => 2]);

        $list = $this->service->list();

        $this->assertCount(2, $list);
        $this->assertSame(1, $list[0]['id']);
        $this->assertSame(2, $list[1]['id']);
        $this->assertSame('ana', $list[1]['username']);
    }
}