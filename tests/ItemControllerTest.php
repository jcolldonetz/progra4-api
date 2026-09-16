<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\ItemController;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\InMemoryCategoriaRepository;
use App\Repositories\InMemoryItemRepository;
use App\Services\ItemService;
use PHPUnit\Framework\TestCase;

final class ItemControllerTest extends TestCase
{
    private ItemController $controller;

    protected function setUp(): void
    {
        $this->controller = new ItemController(new ItemService(new InMemoryItemRepository(), new InMemoryCategoriaRepository()));
    }

    public function testIndexDevuelve200ConElPrimerLote(): void
    {
        $response = $this->controller->index();

        $this->assertSame(200, $response->status);
        $this->assertSame(3, $response->data['meta']['total']);
        $this->assertCount(3, $response->data['data']);
        $this->assertSame('Teclado mecanico', $response->data['data'][0]['nombre']);
    }

    public function testIndexDevuelveUnaPaginaDePerPage(): void
    {
        $response = $this->controller->index(1, 2);

        $this->assertSame(200, $response->status);
        $this->assertCount(2, $response->data['data']);
        $this->assertSame([
            'page'        => 1,
            'per_page'    => 2,
            'total'       => 3,
            'total_pages' => 2,
        ], $response->data['meta']);
    }

    public function testShowDevuelve200ConElItem(): void
    {
        $response = $this->controller->show('2');

        $this->assertSame(200, $response->status);
        $this->assertSame('Mouse inalambrico', $response->data['nombre']);
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

    public function testStoreDevuelve201ConElItemCreado(): void
    {
        $response = $this->controller->store(['nombre' => 'Lampara LED', 'precio' => 12.5]);

        $this->assertSame(201, $response->status);
        $this->assertSame(4, $response->data['id']);
        $this->assertSame('Lampara LED', $response->data['nombre']);
    }

    public function testStoreConDatosInvalidosLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->store(['nombre' => '', 'precio' => -2]);
    }

    public function testUpdateDevuelve200ConElItemActualizado(): void
    {
        $response = $this->controller->update('1', ['nombre' => 'Teclado RGB', 'precio' => 45.9]);

        $this->assertSame(200, $response->status);
        $this->assertSame('Teclado RGB', $response->data['nombre']);
    }

    public function testUpdateInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->update('555', ['nombre' => 'Nuevo', 'precio' => 1]);
    }

    public function testUpdateConNombreDuplicadoLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller->update('1', ['nombre' => 'Mouse inalambrico', 'precio' => 1]);
    }

    public function testDestroyDevuelve204SinCuerpo(): void
    {
        $response = $this->controller->destroy('3');

        $this->assertSame(204, $response->status);
        $this->assertNull($response->data);
    }

    public function testDestroyInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->controller->destroy('777');
    }
}