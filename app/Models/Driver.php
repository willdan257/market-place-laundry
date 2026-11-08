<?php

namespace App\Models;

class Driver extends BaseModel
{
    protected string $table = 'drivers';

    public function findByUserId(int $userId): ?array
    {
        $results = $this->query("
            SELECT d.*, u.name as driver_name, u.email as driver_email, u.phone as driver_phone 
            FROM {$this->table} d
            JOIN users u ON d.user_id = u.id
            WHERE d.user_id = :user_id LIMIT 1
        ", ['user_id' => $userId]);

        return $results[0] ?? null;
    }

    public function findWithUser(int $driverId): ?array
    {
        $results = $this->query("
            SELECT d.*, u.name as driver_name, u.email as driver_email, u.phone as driver_phone 
            FROM {$this->table} d
            JOIN users u ON d.user_id = u.id
            WHERE d.id = :id LIMIT 1
        ", ['id' => $driverId]);

        return $results[0] ?? null;
    }

    /**
     * Mengambil semua kurir yang telah disetujui admin dan berstatus aktif.
     */
    public function getAssignableDrivers(): array
    {
        return $this->query("
            SELECT 
                d.*, 
                u.name as driver_name, 
                u.email as driver_email, 
                u.phone as driver_phone,
                c.status_verifikasi as courier_status,
                c.jenis_kendaraan,
                c.plat_nomor
            FROM {$this->table} d
            JOIN users u ON d.user_id = u.id
            JOIN couriers c ON d.user_id = c.user_id
            WHERE c.status_verifikasi = 'disetujui' AND u.status = 'aktif'
            ORDER BY d.is_available DESC, d.id ASC
        ");
    }

    /**
     * Mencari kurir tertentu yang telah disetujui admin dan akunnya aktif.
     */
    public function findAssignableDriver(int $driverId): ?array
    {
        $results = $this->query("
            SELECT 
                d.*, 
                u.name as driver_name, 
                u.email as driver_email, 
                u.phone as driver_phone,
                c.status_verifikasi as courier_status,
                c.jenis_kendaraan,
                c.plat_nomor
            FROM {$this->table} d
            JOIN users u ON d.user_id = u.id
            JOIN couriers c ON d.user_id = c.user_id
            WHERE d.id = :id AND c.status_verifikasi = 'disetujui' AND u.status = 'aktif'
            LIMIT 1
        ", ['id' => $driverId]);

        return $results[0] ?? null;
    }

    public function getAllDriversWithUser(): array
    {
        return $this->query("
            SELECT d.*, u.name as driver_name, u.email as driver_email, u.phone as driver_phone
            FROM {$this->table} d
            JOIN users u ON d.user_id = u.id
            ORDER BY d.is_available DESC, d.id ASC
        ");
    }
}
