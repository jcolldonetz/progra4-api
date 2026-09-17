<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\User;
use PDO;

/*
 * IMPLEMENTACIÓN VOLÁTIL de UserRepositoryInterface:
 * SQLite EN MEMORIA ('sqlite::memory:') vía PDO. Se siembra en cada arranque.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL
        )
        SQL;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = PdoFactory::create('sqlite::memory:');
        $this->pdo->exec(self::SCHEMA_SQL);
        $this->seed();
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

    private function seed(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $insert->execute([
            ':username'      => 'admin',
            ':password_hash' => password_hash('qwerty67', PASSWORD_BCRYPT),
        ]);
    }
}
