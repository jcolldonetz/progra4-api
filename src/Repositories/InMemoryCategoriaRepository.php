<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\Categoria;
use PDO;

/*
 * IMPLEMENTACIÓN VOLÁTIL de CategoriaRepositoryInterface:
 * SQLite EN MEMORIA ('sqlite::memory:'). Mismo contrato que la versión
 * persistente; los datos viven solo mientras dura el proceso.
 */
final class InMemoryCategoriaRepository implements CategoriaRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE categorias (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL
        )
        SQL;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = PdoFactory::create('sqlite::memory:');
        $this->pdo->exec(self::SCHEMA_SQL);
        $this->seed();
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

    private function seed(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        foreach (['Informática', 'Periféricos', 'Oficina'] as $nombre) {
            $insert->execute([':nombre' => $nombre]);
        }
    }
}