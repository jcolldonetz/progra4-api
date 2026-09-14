<?php

declare(strict_types=1);

namespace App\Tests;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\InMemoryCategoriaRepository;
use App\Repositories\InMemoryItemRepository;
use App\Services\CategoriaService;
use App\Services\ItemService;
use PHPUnit\Framework\TestCase;

final class CategoriaServiceTest extends TestCase
{
    private CategoriaService $service;
    private ItemService $items;

    protected function setUp(): void
    {
        // InMemoryCategoriaRepository siembra: 1=Informática, 2=Periféricos, 3=Oficina.
        // InMemoryItemRepository siembra 3 items SIN categoria asociada.
        $categorias = new InMemoryCategoriaRepository();
        $itemRepo   = new InMemoryItemRepository();

        $this->service = new CategoriaService($categorias, $itemRepo);
        $this->items   = new ItemService($itemRepo, $categorias);
    }

    public function testListAllDevuelveTresCategoriasConItemsCount(): void
    {
        $cats = $this->service->listAll();

        $this->assertCount(3, $cats);
        $this->assertSame('Informática', $cats[0]['nombre']);
        $this->assertSame(['id', 'nombre', 'items_count'], array_keys($cats[0]));
        foreach ($cats as $cat) {
            $this->assertSame(0, $cat['items_count']);
        }
    }

    public function testGetByIdDevuelve200ConCount(): void
    {
        $cat = $this->service->getById(2);

        $this->assertSame('Periféricos', $cat['nombre']);
        $this->assertArrayHasKey('items_count', $cat);
    }

    public function testGetByIdInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No existe la categoria con id 999.');

        $this->service->getById(999);
    }

    public function testListItemsDevuelveLosItemsDeLaCategoria(): void
    {
        $this->items->create(['nombre' => 'Teclado USB', 'precio' => 30.0, 'categoria_id' => 2]);
        $this->items->create(['nombre' => 'Mouse Pad', 'precio' => 5.0, 'categoria_id' => 2]);

        $items = $this->service->listItems(2);

        $this->assertCount(2, $items);
        $this->assertSame(['id', 'nombre', 'precio', 'categoria_id'], array_keys($items[0]));
        $this->assertSame('Teclado USB', $items[0]['nombre']);
    }

    public function testListItemsDeCategoriaVaciaDevuelveListaVacia(): void
    {
        $this->assertSame([], $this->service->listItems(3));
    }

    public function testListItemsDeCategoriaInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->listItems(999);
    }

    public function testCreateDevuelveLaCategoriaCreada(): void
    {
        $created = $this->service->create(['nombre' => '  Redes  ']);

        $this->assertSame(4, $created['id']);
        $this->assertSame('Redes', $created['nombre']);
        $this->assertSame(4, $this->service->getById(4)['id']);
    }

    public function testCreateNombreObligatorioLanza422(): void
    {
        $this->expectException(ValidationException::class);

        foreach ([['nombre' => ''], ['nombre' => '   '], []] as $data) {
            $this->assertErrors(
                fn () => $this->service->create($data),
                'nombre'
            );
        }
    }

    public function testCreateNombreDuplicadoLanza422(): void
    {
        try {
            $this->service->create(['nombre' => 'informática']);
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(["Ya existe una categoria con el nombre 'informática'."], $e->getErrors()['nombre']);
        }
    }

    public function testUpdateReasignaNombre(): void
    {
        $updated = $this->service->update(3, ['nombre' => 'Escritorio']);

        $this->assertSame('Escritorio', $updated['nombre']);
        $this->assertSame(3, $this->service->getById(3)['id']);
    }

    public function testUpdateInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->update(555, ['nombre' => 'Nueva']);
    }

    public function testUpdateConNombreDeOtraCategoriaLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->update(1, ['nombre' => 'Oficina']);
    }

    public function testUpdateConservandoElPropioNombreEsValido(): void
    {
        $updated = $this->service->update(1, ['nombre' => 'Informática']);

        $this->assertSame('Informática', $updated['nombre']);
    }

    public function testDeleteCategoriaSinItemsDevuelve200(): void
    {
        $this->service->delete(3);

        $this->assertCount(2, $this->service->listAll());
    }

    public function testDeleteInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->delete(999);
    }

    public function testDeleteCategoriaConItemsLanza422(): void
    {
        $created = $this->service->create(['nombre' => 'Corporativa']);
        $this->items->create(['nombre' => 'PC Escritorio', 'precio' => 800.0, 'categoria_id' => $created['id']]);

        try {
            $this->service->delete($created['id']);
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['No se puede eliminar la categoria: tiene items asociados.'], $e->getErrors()['categoria_id']);
        }
    }

    public function testItemsCountReflejaLosItemsAsociados(): void
    {
        $created = $this->service->create(['nombre' => 'Corporativa']);
        $this->items->create(['nombre' => 'PC Escritorio', 'precio' => 800.0, 'categoria_id' => $created['id']]);
        $this->items->create(['nombre' => 'Notebook', 'precio' => 1100.0, 'categoria_id' => $created['id']]);

        $this->assertSame(2, $this->service->getById($created['id'])['items_count']);
    }

    /** Fuerza la validación y exige que el campo aparezca en los errores. */
    private function assertErrors(callable $fn, string $field): void
    {
        try {
            $fn();
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->getErrors());
            throw $e;
        }
    }
}