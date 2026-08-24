<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\Item;
use PDO;

/*
 * IMPLEMENTACIÓN VOLÁTIL: usa SQLite EN MEMORIA ('sqlite::memory:') vía PDO.
 * Cumple exactamente el mismo contrato que SqliteItemRepository; la única
 * diferencia es el DSN de la conexión. Ideal para demos y pruebas:
 * los datos existen solo mientras vive el proceso.
 */
final class InMemoryItemRepository implements ItemRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE items (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            precio REAL NOT NULL CHECK (precio >= 0)
        )
        SQL;

    private PDO $pdo;

    public function __construct()
    {
        // La "base de datos" vive en RAM: no se crea ningún archivo.
        $this->pdo = PdoFactory::create('sqlite::memory:');
        $this->pdo->exec(self::SCHEMA_SQL);
        $this->seed();
    }

    public function findAll(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, nombre, precio FROM items ORDER BY id')
            ->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    public function findById(int $id): ?Item
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, precio FROM items WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByName(string $nombre): ?Item
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, precio FROM items WHERE lower(nombre) = lower(:nombre)'
        );
        $stmt->execute([':nombre' => $nombre]);

        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function create(Item $item): Item
    {
        $stmt = $this->pdo->prepare('INSERT INTO items (nombre, precio) VALUES (:nombre, :precio)');
        $stmt->execute([
            ':nombre' => $item->getNombre(),
            ':precio' => $item->getPrecio(),
        ]);

        return new Item((int) $this->pdo->lastInsertId(), $item->getNombre(), $item->getPrecio());
    }

    public function update(Item $item): Item
    {
        $stmt = $this->pdo->prepare('UPDATE items SET nombre = :nombre, precio = :precio WHERE id = :id');
        $stmt->execute([
            ':nombre' => $item->getNombre(),
            ':precio' => $item->getPrecio(),
            ':id'     => $item->getId(),
        ]);

        return $item;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM items WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): Item
    {
        return new Item((int) $row['id'], (string) $row['nombre'], (float) $row['precio']);
    }

    private function seed(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO items (nombre, precio) VALUES (:nombre, :precio)');
        foreach ([
            ['Teclado mecanico', 25.50],
            ['Mouse inalambrico', 15.90],
            ['Monitor 24"', 189.99],
        ] as [$nombre, $precio]) {
            $insert->execute([':nombre' => $nombre, ':precio' => $precio]);
        }
    }
}
