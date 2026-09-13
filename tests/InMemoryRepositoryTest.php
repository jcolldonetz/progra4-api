<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Item;
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
        $this->assertTrue(password_verify('1234', $admin->getPasswordHash()));
        $this->assertNull($repo->findByUsername('inexistente'));
    }

    public function testUserSaveDevuelveIdYPermiteRecuperar(): void
    {
        $repo = new InMemoryUserRepository();

        $id = $repo->save('ana', password_hash('clave', PASSWORD_BCRYPT));

        $this->assertSame(2, $id);
        $this->assertSame('ana', $repo->findByUsername('ana')?->getUsername());
    }
}