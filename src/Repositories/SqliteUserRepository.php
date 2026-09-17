<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\User;
use PDO;

/*
 * IMPLEMENTACIÓN PERSISTENTE de UserRepositoryInterface.
 * Guarda los usuarios en el mismo ARCHIVO SQLite que los items.
 *
 * Punto clave para la clase: la contraseña NO se guarda en texto plano.
 * En la BD solo existe su HASH bcrypt; al sembrar el usuario "admin"
 * se calcula con password_hash() y al validar se usa password_verify().
 */
final class SqliteUserRepository implements UserRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL
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

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);

        $row = $stmt->fetch();
        return $row === false ? null : new User((int) $row['id'], (string) $row['username'], (string) $row['password_hash']);
    }

    public function save(string $username, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $stmt->execute([':username' => $username, ':password_hash' => $passwordHash]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedIfEmpty(): void
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($total > 0) {
            return;
        }

        $insert = $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $insert->execute([
            ':username'      => 'admin',
            ':password_hash' => password_hash('qwerty67', PASSWORD_BCRYPT),
        ]);
    }
}
