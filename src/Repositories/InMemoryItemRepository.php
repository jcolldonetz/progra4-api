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
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre       TEXT NOT NULL,
            precio       REAL NOT NULL CHECK (precio >= 0),
            categoria_id INTEGER REFERENCES categorias(id)
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

    public function findPage(int $offset, int $limit): array
    {
        return array_values(array_slice($this->findAll(), $offset, $limit));
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    }

    public function findPageByCategoria(int $categoriaId, int $offset, int $limit): array
    {
        return array_values(array_slice($this->findByCategoria($categoriaId), $offset, $limit));
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

    public function findPageFiltered(?int $categoriaId, ?string $search, int $offset, int $limit): array
    {
        return array_values(array_slice($this->filterAll($categoriaId, $search), $offset, $limit));
    }

    public function countFiltered(?int $categoriaId, ?string $search): int
    {
        return count($this->filterAll($categoriaId, $search));
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

    /** Items que cumplen los filtros opcionales (categoría y texto del nombre). */
    private function filterAll(?int $categoriaId, ?string $search): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn (Item $item): bool =>
                ($categoriaId === null || $item->getCategoriaId() === $categoriaId)
                && ($search === null || $search === '' || stripos($item->getNombre(), $search) !== false),
        ));
    }

    private function hydrate(array $row): Item
    {
        return new Item(
            (int) $row['id'],
            (string) $row['nombre'],
            (float) $row['precio'],
            $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null,
        );
    }

    private function seed(): void
    {
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