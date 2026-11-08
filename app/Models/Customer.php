<?php

namespace App\Models;

class Customer extends BaseModel
{
    protected string $table = 'customers';

    public function findByUserId(int $userId): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1", ['user_id' => $userId]);
        return $results[0] ?? null;
    }

    public function createCustomer(int $userId, ?string $alamatUtama = null): int|bool
    {
        $sql = "INSERT INTO {$this->table} (user_id, alamat_utama) VALUES (:user_id, :alamat_utama)";
        $success = $this->execute($sql, [
            'user_id' => $userId,
            'alamat_utama' => $alamatUtama
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }
}
