<?php

declare(strict_types=1);

namespace App\Tests;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\InMemoryCategoriaRepository;
use App\Repositories\InMemoryItemRepository;
use App\Services\ItemService;
use PHPUnit\Framework\TestCase;

final class ItemServiceTest extends TestCase
{
    private ItemService $service;

    protected function setUp(): void
    {
        // Siembra tres items: "Teclado mecanico", "Mouse inalambrico", "Monitor 24\"".
        $this->service = new ItemService(new InMemoryItemRepository(), new InMemoryCategoriaRepository());
    }

    public function testListAllDevuelveLosItemsSembrados(): void
    {
        $items = $this->service->listAll();

        $this->assertCount(3, $items);
        $this->assertSame('Teclado mecanico', $items[0]['nombre']);
        $this->assertSame(['id', 'nombre', 'precio', 'categoria_id'], array_keys($items[0]));
    }

    public function testGetByIdDevuelveElItem(): void
    {
        $item = $this->service->getById(2);

        $this->assertSame('Mouse inalambrico', $item['nombre']);
    }

    public function testGetByIdInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No existe el item con id 999.');

        $this->service->getById(999);
    }

    public function testCreateDevuelveItemConIdYPersiste(): void
    {
        $created = $this->service->create(['nombre' => 'Lampara LED', 'precio' => 12.5]);

        $this->assertSame(4, $created['id']);
        $this->assertSame('Lampara LED', $created['nombre']);
        $this->assertSame(12.5, $created['precio']);
        $this->assertCount(4, $this->service->listAll());
    }

    public function testCreateNombreDuplicadoIgnoraMayusculas(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertErrors(
            fn () => $this->service->create(['nombre' => 'teclado MECANICO', 'precio' => 1]),
            'nombre'
        );

        try {
            $this->service->create(['nombre' => 'teclado MECANICO', 'precio' => 1]);
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'Ya existe un item con el nombre',
                $e->getErrors()['nombre'][0]
            );
        }
    }

    public function testCreateNombreVacio(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertErrors(
            fn () => $this->service->create(['nombre' => '   ', 'precio' => 1]),
            'nombre'
        );
    }

    public function testCreateNombreInvalido(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertErrors(
            fn () => $this->service->create(['nombre' => 123, 'precio' => 1]),
            'nombre'
        );
    }

    public function testCreateNombreMayorA100(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(['nombre' => str_repeat('x', 101), 'precio' => 1]);
    }

    public function testCreatePrecioNegativo(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La solicitud contiene datos inválidos.');

        $this->assertErrors(
            fn () => $this->service->create(['nombre' => 'X', 'precio' => -1]),
            'precio'
        );
    }

    public function testCreatePrecioNoNumerico(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertErrors(
            fn () => $this->service->create(['nombre' => 'X', 'precio' => 'abc']),
            'precio'
        );
    }

    public function testCreateSinCamposAcumulaTodosLosErrores(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->create([]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nombre', $e->getErrors());
            $this->assertArrayHasKey('precio', $e->getErrors());
            $this->assertSame(422, $e->httpStatus());
            throw $e;
        }
    }

    public function testUpdateReemplazaNombreYPrecio(): void
    {
        $updated = $this->service->update(1, ['nombre' => 'Teclado RGB', 'precio' => 45.9]);

        $this->assertSame('Teclado RGB', $updated['nombre']);
        $this->assertSame(45.9, $updated['precio']);
        $this->assertSame('Teclado RGB', $this->service->getById(1)['nombre']);
    }

    public function testUpdateInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->update(500, ['nombre' => 'No existe', 'precio' => 9]);
    }

    public function testUpdateNombreUsadoPorOtroItem(): void
    {
        try {
            $this->service->update(1, ['nombre' => 'Mouse inalambrico', 'precio' => 1]);
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'Ya existe otro item con el nombre',
                $e->getErrors()['nombre'][0]
            );
        }
    }

    public function testUpdateConservandoElPropioNombreEsValido(): void
    {
        $updated = $this->service->update(1, ['nombre' => 'Teclado mecanico', 'precio' => 30]);

        $this->assertSame('Teclado mecanico', $updated['nombre']);
        $this->assertSame(30.0, $updated['precio']);
    }

    public function testDeleteEliminaItemExistente(): void
    {
        $this->service->delete(1);

        $this->assertCount(2, $this->service->listAll());

        $this->expectException(NotFoundException::class);
        $this->service->getById(1);
    }

    public function testDeleteInexistenteLanza404(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No existe el item con id 900.');

        $this->service->delete(900);
    }

    public function testCreateConCategoriaIdAsociaElItem(): void
    {
        // InMemoryCategoriaRepository siembra: 1=Informática, 2=Periféricos, 3=Oficina.
        $created = $this->service->create(['nombre' => 'Webcam', 'precio' => 40.0, 'categoria_id' => 2]);

        $this->assertSame(2, $created['categoria_id']);
        $this->assertSame(2, $this->service->getById($created['id'])['categoria_id']);
    }

    public function testCreateSinCategoriaDejaCategoriaNula(): void
    {
        $created = $this->service->create(['nombre' => 'Cable HDMI', 'precio' => 8.5]);

        $this->assertNull($created['categoria_id']);
    }

    public function testCreateCategoriaInexistenteLanza422(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertErrors(
            fn () => $this->service->create(['nombre' => 'X', 'precio' => 1, 'categoria_id' => 999]),
            'categoria_id'
        );
    }

    public function testCreateCategoriaIdNoEnteroPositivoLanza422(): void
    {
        $this->expectException(ValidationException::class);

        foreach (['abc', '0', '-3'] as $bad) {
            $this->assertErrors(
                fn () => $this->service->create(['nombre' => 'X', 'precio' => 1, 'categoria_id' => $bad]),
                'categoria_id'
            );
        }
    }

    public function testUpdateReasignaCategoria(): void
    {
        $updated = $this->service->update(1, ['nombre' => 'Teclado RGB', 'precio' => 45.9, 'categoria_id' => 1]);

        $this->assertSame(1, $updated['categoria_id']);
        $this->assertSame(1, $this->service->getById(1)['categoria_id']);
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