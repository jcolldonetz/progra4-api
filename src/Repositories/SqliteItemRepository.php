<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\Item;
use PDO;

/*
 * IMPLEMENTACIÓN PERSISTENTE: guarda los items en un ARCHIVO SQLite vía PDO.
 * Los datos sobreviven entre ejecuciones del servidor.
 *
 * En esta versión la tabla items tiene la FK `categoria_id` (relación 1:N):
 * - Las bases nuevas la crean en el CREATE TABLE.
 * - Las bases viejas la obtienen con un ALTER TABLE en migrateItemsTable().
 */
final class SqliteItemRepository implements ItemRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS items (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre       TEXT NOT NULL,
            precio       REAL NOT NULL CHECK (precio >= 0),
            categoria_id INTEGER REFERENCES categorias(id)
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
        $this->migrateItemsTable();
        $this->seedIfEmpty();
    }

    public function findAll(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, nombre, precio, categoria_id FROM items ORDER BY id')
            ->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    public function findById(int $id): ?Item
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, precio, categoria_id FROM items WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByName(string $nombre): ?Item
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, precio, categoria_id FROM items WHERE lower(nombre) = lower(:nombre)'
        );
        $stmt->execute([':nombre' => $nombre]);

        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByCategoria(int $categoriaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, precio, categoria_id FROM items WHERE categoria_id = :categoria_id ORDER BY id'
        );
        $stmt->execute([':categoria_id' => $categoriaId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countByCategoria(int $categoriaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM items WHERE categoria_id = :categoria_id');
        $stmt->execute([':categoria_id' => $categoriaId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(Item $item): Item
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO items (nombre, precio, categoria_id) VALUES (:nombre, :precio, :categoria_id)'
        );
        $stmt->execute([
            ':nombre'       => $item->getNombre(),
            ':precio'       => $item->getPrecio(),
            ':categoria_id' => $item->getCategoriaId(),
        ]);

        return new Item(
            (int) $this->pdo->lastInsertId(),
            $item->getNombre(),
            $item->getPrecio(),
            $item->getCategoriaId(),
        );
    }

    public function update(Item $item): Item
    {
        $stmt = $this->pdo->prepare(
            'UPDATE items SET nombre = :nombre, precio = :precio, categoria_id = :categoria_id WHERE id = :id'
        );
        $stmt->execute([
            ':nombre'       => $item->getNombre(),
            ':precio'       => $item->getPrecio(),
            ':categoria_id' => $item->getCategoriaId(),
            ':id'           => $item->getId(),
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
        return new Item(
            (int) $row['id'],
            (string) $row['nombre'],
            (float) $row['precio'],
            $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null,
        );
    }

    /**
     * Asegura que items tenga la columna categoria_id (bases creadas antes
     * de la relación 1:N con categorias). SQLite no tiene
     * "ADD COLUMN IF NOT EXISTS", por eso se consulta PRAGMA table_info().
     */
    private function migrateItemsTable(): void
    {
        $columns = $this->pdo->query('PRAGMA table_info(items)')->fetchAll();
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'categoria_id') {
                return;
            }
        }

        $this->pdo->exec('ALTER TABLE items ADD COLUMN categoria_id INTEGER REFERENCES categorias(id)');
    }

    /** Datos de ejemplo para la demo, solo si la tabla está vacía. */
    private function seedIfEmpty(): void
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        if ($total > 0) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO items (nombre, precio, categoria_id) VALUES (:nombre, :precio, :categoria_id)'
        );
        foreach ([
            ['Teclado mecanico', 25.50, null],
            ['Mouse inalambrico', 15.90, null],
            ['Monitor 24"', 189.99, null],
        ] as [$nombre, $precio, $categoriaId]) {
            $insert->execute([
                ':nombre'       => $nombre,
                ':precio'       => $precio,
                ':categoria_id' => $categoriaId,
            ]);
        }
    }
}