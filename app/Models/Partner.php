<?php

namespace App\Models;

class Partner extends BaseModel
{
    protected string $table = 'partners';

    public function findByUserId(int $userId): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1", ['user_id' => $userId]);
        return $results[0] ?? null;
    }

    public function createPartner(array $data): int|bool
    {
        $sql = "INSERT INTO {$this->table} (user_id, nama_usaha, alamat, lat, lng, jam_operasional, dokumen, status_verifikasi)
                VALUES (:user_id, :nama_usaha, :alamat, :lat, :lng, :jam_operasional, :dokumen, :status_verifikasi)";

        $success = $this->execute($sql, [
            'user_id'           => $data['user_id'],
            'nama_usaha'        => $data['nama_usaha'],
            'alamat'            => $data['alamat'],
            'lat'               => $data['lat'] ?? null,
            'lng'               => $data['lng'] ?? null,
            'jam_operasional'   => $data['jam_operasional'] ?? null,
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
