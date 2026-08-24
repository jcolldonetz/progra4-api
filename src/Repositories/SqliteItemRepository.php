<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\Item;
use PDO;

/*
 * IMPLEMENTACIÓN PERSISTENTE: guarda los items en un ARCHIVO SQLite vía PDO.
 * Los datos sobreviven entre ejecuciones del servidor.
 */
final class SqliteItemRepository implements ItemRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS items (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            precio REAL NOT NULL CHECK (precio >= 0)
        )
        SQL;

    private PDO $pdo;

    public function __construct(string $dbFile)
    {
        $directory = dirname($dbFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $this->pdo = PdoFactory::create('sqlite:' . $dbFile);
        $this->pdo->exec(self::SCHEMA_SQL);
        $this->seedIfEmpty();
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

    /** Convierte una fila de la BD en una entidad del dominio. */
    private function hydrate(array $row): Item
    {
        return new Item((int) $row['id'], (string) $row['nombre'], (float) $row['precio']);
    }

    /** Datos de ejemplo para la demo, solo si la tabla está vacía. */
    private function seedIfEmpty(): void
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        if ($total > 0) {
            return;
        }

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
