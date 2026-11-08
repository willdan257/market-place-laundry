<?php

namespace App\Models;

use PDO;
use PDOException;
use Exception;

abstract class BaseModel
{
    protected static ?PDO $pdo = null;
    protected string $table = '';

    public function __construct()
    {
        self::initConnection();
    }

    public static function getPdo(): ?PDO
    {
        self::initConnection();
        return self::$pdo;
    }

    private static function initConnection(): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $config = require __DIR__ . '/../../config/database.php';

        try {
            $dsn = sprintf(
                "%s:host=%s;port=%s;dbname=%s;charset=%s",
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            self::$pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            // Graceful handling if DB fails or does not exist yet
            error_log("[BaseModel] Database connection failed: " . $e->getMessage());
            self::$pdo = null;
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function find(int $id): ?array
    {
        if (empty($this->table)) {
            return null;
        }
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $results = $this->query($sql, ['id' => $id]);
        return $results[0] ?? null;
    }

    public function all(): array
    {
        if (empty($this->table)) {
            return [];
        }
        return $this->query("SELECT * FROM {$this->table}");
    }

    public static function beginTransaction(): bool
    {
        $pdo = self::getPdo();
        if ($pdo && !$pdo->inTransaction()) {
            return $pdo->beginTransaction();
        }
        return false;
    }

    public static function commit(): bool
    {
        $pdo = self::getPdo();
        if ($pdo && $pdo->inTransaction()) {
            return $pdo->commit();
        }
        return false;
    }

    public static function rollBack(): bool
    {
        $pdo = self::getPdo();
        if ($pdo && $pdo->inTransaction()) {
            return $pdo->rollBack();
        }
        return false;
    }

    public static function inTransaction(): bool
    {
        $pdo = self::getPdo();
        return $pdo ? $pdo->inTransaction() : false;
    }
}
