<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Categoria;
use App\Models\Item;
use App\Repositories\InMemoryCategoriaRepository;
use App\Repositories\InMemoryItemRepository;
use App\Repositories\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryRepositoryTest extends TestCase
{
    public function testItemListaTodosYTodosTienenId(): void
    {
        $repo = new InMemoryItemRepository();

        $items = $repo->findAll();
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertInstanceOf(Item::class, $item);
            $this->assertNotNull($item->getId());
        }
    }

    public function testItemFindByIdYFindByName(): void
    {
        $repo = new InMemoryItemRepository();

        $this->assertSame('Teclado mecanico', $repo->findById(1)?->getNombre());
        $this->assertNull($repo->findById(999));

        // findByName ignora mayúsculas/minúsculas.
        $this->assertSame('Mouse inalambrico', $repo->findByName('mOUSE INALAMBRICO')?->getNombre());
        $this->assertNull($repo->findByName('No existe'));
    }

    public function testItemCreateAsignaIdSecuencial(): void
    {
        $repo = new InMemoryItemRepository();

        $created = $repo->create(new Item(null, 'Webcam', 40.0));

        $this->assertSame(4, $created->getId());
        $this->assertCount(4, $repo->findAll());
    }

    public function testItemFindByCategoriaYCount(): void
    {
        $repo = new InMemoryItemRepository();

        $repo->create(new Item(null, 'Monitor A', 100.0, 1));
        $repo->create(new Item(null, 'Monitor B', 200.0, 1));
        $repo->create(new Item(null, 'Teclado RGB', 50.0, 2));

        $this->assertCount(2, $repo->findByCategoria(1));
        $this->assertCount(1, $repo->findByCategoria(2));
        $this->assertSame(0, $repo->countByCategoria(999));
        $this->assertSame(2, $repo->countByCategoria(1));
    }

    public function testItemUpdatePersisteLosCambios(): void
    {
        $repo = new InMemoryItemRepository();

        $repo->update(new Item(1, 'Teclado RGB', 45.9));

        $updated = $repo->findById(1);
        $this->assertSame('Teclado RGB', $updated->getNombre());
        $this->assertSame(45.9, $updated->getPrecio());
    }

    public function testItemDeleteDevuelveBooleano(): void
    {
        $repo = new InMemoryItemRepository();

        $this->assertTrue($repo->delete(2));
        $this->assertFalse($repo->delete(2));
        $this->assertCount(2, $repo->findAll());
    }

    public function testUserAdminSembrado(): void
    {
        $repo = new InMemoryUserRepository();

        $admin = $repo->findByUsername('admin');
        $this->assertNotNull($admin);
        $this->assertSame(1, $admin->getId());
        $this->assertTrue(password_verify('qwerty67', $admin->getPasswordHash()));
        $this->assertNull($repo->findByUsername('inexistente'));
    }

    public function testUserSaveDevuelveIdYPermiteRecuperar(): void
    {
        $repo = new InMemoryUserRepository();

        $id = $repo->save('ana', password_hash('clave', PASSWORD_BCRYPT));

        $this->assertSame(2, $id);
        $this->assertSame('ana', $repo->findByUsername('ana')?->getUsername());
    }

    public function testCategoriaCrudBasico(): void
    {
        $repo = new InMemoryCategoriaRepository();

        $this->assertCount(3, $repo->findAll());
        $this->assertSame('Informática', $repo->findById(1)?->getNombre());
        $this->assertNull($repo->findById(999));

        $created = $repo->create(new Categoria(null, 'Redes'));
        $this->assertSame(4, $created->getId());

        $repo->update(new Categoria(4, 'Redes avanzadas'));
        $this->assertSame('Redes avanzadas', $repo->findById(4)?->getNombre());

        $this->assertTrue($repo->delete(4));
        $this->assertFalse($repo->delete(4));
    }
}