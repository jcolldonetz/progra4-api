<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\PdoFactory;
use App\Models\Pedido;
use PDO;

/*
 * IMPLEMENTACIÓN PERSISTENTE: guarda los pedidos en el ARCHIVO SQLite vía PDO.
 * Los datos sobreviven entre ejecuciones del servidor.
 *
 * `placeOrder` es atómico: dentro de UNA transacción sobre la misma conexión
 * se (1) descuenta stock con una actualización guardada (solo si alcanza) y
 * (2) inserta el pedido. Si el descuento no afecta ninguna fila (item inexistente
 * o stock insuficiente) se hace rollback y nada se modifica.
 */
final class SqlitePedidoRepository implements PedidoRepositoryInterface
{
    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS pedidos (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id    INTEGER NOT NULL REFERENCES items(id),
            cantidad   INTEGER NOT NULL CHECK (cantidad >= 1),
            total      REAL NOT NULL CHECK (total >= 0),
            username   TEXT NOT NULL,
            created_at TEXT NOT NULL
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
    }

    public function placeOrder(int $itemId, int $cantidad, string $username): ?Pedido
    {
        $this->pdo->beginTransaction();

        try {
            $price = $this->pdo->prepare('SELECT precio FROM items WHERE id = :item_id');
            $price->execute([':item_id' => $itemId]);
            $precio = $price->fetchColumn();
            if ($precio === false) {
                $this->pdo->rollBack();
                return null;
            }

            $decrement = $this->pdo->prepare(
                'UPDATE items SET stock = stock - :cantidad WHERE id = :item_id AND stock >= :cantidad'
            );
            $decrement->execute([':cantidad' => $cantidad, ':item_id' => $itemId]);

            if ($decrement->rowCount() === 0) {
                $this->pdo->rollBack();
                return null;
            }

            $createdAt = gmdate('c');
            $insert = $this->pdo->prepare(
                'INSERT INTO pedidos (item_id, cantidad, total, username, created_at)
                 VALUES (:item_id, :cantidad, :total, :username, :created_at)'
            );
            $insert->execute([
                ':item_id'    => $itemId,
                ':cantidad'   => $cantidad,
                ':total'      => (float) $precio * $cantidad,
                ':username'   => $username,
                ':created_at' => $createdAt,
            ]);

            $this->pdo->commit();

            return new Pedido(
                (int) $this->pdo->lastInsertId(),
                $itemId,
                $cantidad,
                (float) $precio * $cantidad,
                $username,
                $createdAt,
            );
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findAll(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, item_id, cantidad, total, username, created_at FROM pedidos ORDER BY id')
            ->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    private function hydrate(array $row): Pedido
    {
        return new Pedido(
            (int) $row['id'],
            (int) $row['item_id'],
            (int) $row['cantidad'],
            (float) $row['total'],
            (string) $row['username'],
            (string) $row['created_at'],
        );
    }
}