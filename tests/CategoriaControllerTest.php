<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\CategoriaController;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\InMemoryCategoriaRepository;
use App\Repositories\InMemoryItemRepository;
use App\Services\CategoriaService;
use App\Services\ItemService;
use PHPUnit\Framework\TestCase;

final class CategoriaControllerTest extends TestCase
{
    private CategoriaController $controller;
    private ItemService $items;

    protected function setUp(): void
    {
        $categorias  = new InMemoryCategoriaRepository();
        $itemsRepo   = new InMemoryItemRepository();

        $this->controller = new CategoriaController(new CategoriaService($categorias, $itemsRepo));
        $this->items      = new ItemService($itemsRepo, $categorias);
    }

    public function testIndexDevuelve200ConLaLista(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->status);
        $this->assertCount(3, $response->data);
        $this->assertSame('Informática', $response->data[0]['nombre']);
        $this->assertArrayHasKey('items_count', $response->data[0]);
    }

    public function testShowDevuelve200ConLaCategoria(): void
    {
        $response = $this->controller->show('2');

        $this->assertSame(200, $response->status);
        $this->assertSame('Periféricos', $response->data['nombre']);
    }

    public function testShowInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->show('999');
    }

    public function testShowConIdInvalidoLanza422(): void
    {
        $this->expectException(ValidationException::class);

        foreach (['abc', '0', '-5', '1.5'] as $id) {
            $this->controller->show($id);
        }
    }

    public function testItemsDevuelve200ConLosItemsDeLaCategoria(): void
    {
        $cat = $this->controller->store(['nombre' => 'Monitores']);
        $this->items->create(['nombre' => 'Monitor 27"', 'precio' => 150.0, 'categoria_id' => $cat->data['id']]);

        $response = $this->controller->items((string) $cat->data['id']);

        $this->assertSame(200, $response->status);
        $this->assertCount(1, $response->data['data']);
        $this->assertSame('Monitor 27"', $response->data['data'][0]['nombre']);
        $this->assertSame(1, $response->data['meta']['total']);
    }

    public function testItemsDeCategoriaInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->items('999');
    }

    public function testStoreDevuelve201ConLaCategoriaCreada(): void
    {
        $response = $this->controller->store(['nombre' => 'Redes']);

        $this->assertSame(201, $response->status);
        $this->assertSame(4, $response->data['id']);
        $this->assertSame('Redes', $response->data['nombre']);
    }

    public function testStoreConDatosInvalidosLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->store(['nombre' => '']);
    }

    public function testUpdateDevuelve200ConLaCategoriaActualizada(): void
    {
        $response = $this->controller->update('3', ['nombre' => 'Escritorio']);

        $this->assertSame(200, $response->status);
        $this->assertSame('Escritorio', $response->data['nombre']);
    }

    public function testUpdateInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->update('555', ['nombre' => 'Nueva']);
    }

    public function testUpdateConNombreDuplicadoLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->update('1', ['nombre' => 'Oficina']);
    }

    public function testDestroyDevuelve204SinCuerpo(): void
    {
        $response = $this->controller->destroy('3');

        $this->assertSame(204, $response->status);
        $this->assertNull($response->data);
    }

    public function testDestroyCategoriaConItemsLanza422(): void
    {
        $cat = $this->controller->store(['nombre' => 'Corporativa']);
        $this->items->create(['nombre' => 'PC', 'precio' => 500.0, 'categoria_id' => $cat->data['id']]);

        $this->expectException(ValidationException::class);

        $this->controller->destroy((string) $cat->data['id']);
    }

    public function testDestroyInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->destroy('777');
    }
}