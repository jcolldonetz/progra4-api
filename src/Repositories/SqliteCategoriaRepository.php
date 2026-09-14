<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\Categoria;
use PDO;

/*
 * IMPLEMENTACIÓN PERSISTENTE para categorias: archivo SQLite vía PDO.
 * La tabla items apunta aquí con FK `categoria_id` (relación 1:N).
 */
final class SqliteCategoriaRepository implements CategoriaRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS categorias (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL
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
            ->query('SELECT id, nombre FROM categorias ORDER BY id')
            ->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    public function findById(int $id): ?Categoria
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre FROM categorias WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByName(string $nombre): ?Categoria
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre FROM categorias WHERE lower(nombre) = lower(:nombre)'
        );
        $stmt->execute([':nombre' => $nombre]);

        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function create(Categoria $categoria): Categoria
    {
        $stmt = $this->pdo->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        $stmt->execute([':nombre' => $categoria->getNombre()]);

        return new Categoria((int) $this->pdo->lastInsertId(), $categoria->getNombre());
    }

    public function update(Categoria $categoria): Categoria
    {
        $stmt = $this->pdo->prepare('UPDATE categorias SET nombre = :nombre WHERE id = :id');
        $stmt->execute([
            ':nombre' => $categoria->getNombre(),
            ':id'     => $categoria->getId(),
        ]);

        return $categoria;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM categorias WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): Categoria
    {
        return new Categoria((int) $row['id'], (string) $row['nombre']);
    }

    /**
     * Asegura que items tenga la columna categoria_id.
     * SQLite no permite "ADD COLUMN IF NOT EXISTS", por eso se consulta PRAGMA
     * y se agrega la columna SOLO si la BD es anterior a esta versión.
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
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
        if ($total > 0) {
            return;
        }

        $insert = $this->pdo->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        foreach (['Informática', 'Periféricos', 'Oficina'] as $nombre) {
            $insert->execute([':nombre' => $nombre]);
        }
    }
}