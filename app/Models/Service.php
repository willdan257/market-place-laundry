<?php

namespace App\Models;

class Service extends BaseModel
{
    protected string $table = 'services';

    public function getActiveServices(): array
    {
        return $this->query("
            SELECT s.*, st.store_name, st.full_address as store_address 
            FROM {$this->table} s
            JOIN stores st ON s.store_id = st.id
            WHERE s.is_active = 1 AND st.is_open = 1
            ORDER BY st.id ASC, s.id ASC
        ");
    }

    /**
     * Mengambil layanan aktif milik toko tertentu yang buka dan telah disetujui.
     */
    public function getActiveByStore(int $storeId): array
    {
        return $this->query("
            SELECT s.*, st.store_name, st.full_address as store_address, st.latitude as store_latitude, st.longitude as store_longitude
            FROM {$this->table} s
            JOIN stores st ON s.store_id = st.id
            JOIN partners p ON st.owner_id = p.user_id
            WHERE s.store_id = :store_id 
              AND s.is_active = 1 
              AND st.is_open = 1 
              AND p.status_verifikasi = 'disetujui'
            ORDER BY s.id ASC
        ", ['store_id' => $storeId]);
    }

    public function findWithStore(int $serviceId): ?array
    {
        $results = $this->query("
            SELECT s.*, st.store_name, st.owner_id, st.full_address as store_address, st.latitude as store_latitude, st.longitude as store_longitude
            FROM {$this->table} s
            JOIN stores st ON s.store_id = st.id
            WHERE s.id = :id AND s.is_active = 1
            LIMIT 1
        ", ['id' => $serviceId]);

        return $results[0] ?? null;
    }

    public function createService(array $data): int|bool
    {
        $sql = "INSERT INTO {$this->table} (store_id, service_name, unit, price_per_unit, estimated_duration_hours, description, is_active)
                VALUES (:store_id, :service_name, :unit, :price_per_unit, :estimated_duration_hours, :description, 1)";

        $success = $this->execute($sql, [
            'store_id'                  => $data['store_id'],
            'service_name'              => $data['service_name'],
            'unit'                      => $data['unit'] ?? 'kg',
            'price_per_unit'            => $data['price_per_unit'],
            'estimated_duration_hours'  => $data['estimated_duration_hours'] ?? 24,
            'description'               => $data['description'] ?? null
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }
}
