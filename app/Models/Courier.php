<?php

namespace App\Models;

class Courier extends BaseModel
{
    protected string $table = 'couriers';

    public function findByUserId(int $userId): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1", ['user_id' => $userId]);
        return $results[0] ?? null;
    }

    public function createCourier(array $data): int|bool
    {
        $sql = "INSERT INTO {$this->table} (user_id, jenis_kendaraan, plat_nomor, dokumen, status_verifikasi)
                VALUES (:user_id, :jenis_kendaraan, :plat_nomor, :dokumen, :status_verifikasi)";

        $success = $this->execute($sql, [
            'user_id'           => $data['user_id'],
            'jenis_kendaraan'   => $data['jenis_kendaraan'] ?? 'Motor',
            'plat_nomor'        => $data['plat_nomor'],
            'dokumen'           => $data['dokumen'] ?? null,
            'status_verifikasi' => $data['status_verifikasi'] ?? 'menunggu_verifikasi'
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }

    public function updateVerificationStatus(int $userId, string $status, ?string $alasan = null): bool
    {
        $sql = "UPDATE {$this->table} 
                SET status_verifikasi = :status, alasan_penolakan = :alasan 
                WHERE user_id = :user_id";
        return $this->execute($sql, [
            'status'  => $status,
            'alasan'  => $alasan,
            'user_id' => $userId
        ]);
    }
}
