<?php

namespace App\Models;

class CustomerAddress extends BaseModel
{
    protected string $table = 'customer_addresses';

    public function getAddressesByUserId(int $userId): array
    {
        return $this->query("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY is_primary DESC, id DESC", ['user_id' => $userId]);
    }

    public function getPrimaryAddress(int $userId): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY is_primary DESC, id ASC LIMIT 1", ['user_id' => $userId]);
        return $results[0] ?? null;
    }

    public function savePrimaryAddress(int $userId, array $data): bool
    {
        $existing = $this->getPrimaryAddress($userId);

        if ($existing) {
            $sql = "UPDATE {$this->table}
                    SET recipient_name = :name, recipient_phone = :phone, full_address = :address,
                        rt = :rt, rw = :rw, district = :district, city = :city,
                        latitude = :lat, longitude = :lng, is_primary = 1
                    WHERE id = :id";

            return $this->execute($sql, [
                'name' => $data['recipient_name'],
                'phone' => $data['recipient_phone'],
                'address' => $data['full_address'],
                'rt' => $data['rt'],
                'rw' => $data['rw'],
                'district' => $data['district'] ?? null,
                'city' => $data['city'] ?? null,
                'lat' => $data['latitude'],
                'lng' => $data['longitude'],
                'id' => $existing['id']
            ]);
        }

        $sql = "INSERT INTO {$this->table} (user_id, label, recipient_name, recipient_phone, full_address, rt, rw, district, city, latitude, longitude, is_primary)
                VALUES (:user_id, 'Utama', :name, :phone, :address, :rt, :rw, :district, :city, :lat, :lng, 1)";

        return $this->execute($sql, [
            'user_id' => $userId,
            'name' => $data['recipient_name'],
            'phone' => $data['recipient_phone'],
            'address' => $data['full_address'],
            'rt' => $data['rt'],
            'rw' => $data['rw'],
            'district' => $data['district'] ?? null,
            'city' => $data['city'] ?? null,
            'lat' => $data['latitude'],
            'lng' => $data['longitude']
        ]);
    }
    public function createAddress(array $data): int|bool
    {
        $sql = "INSERT INTO {$this->table} (user_id, label, recipient_name, recipient_phone, full_address, rt, rw, district, city, postal_code, latitude, longitude, is_primary)
                VALUES (:user_id, :label, :recipient_name, :recipient_phone, :full_address, :rt, :rw, :district, :city, :postal_code, :latitude, :longitude, :is_primary)";

        $success = $this->execute($sql, [
            'user_id'          => $data['user_id'],
            'label'            => $data['label'] ?? 'Utama',
            'recipient_name'   => $data['recipient_name'],
            'recipient_phone'  => $data['recipient_phone'],
            'full_address'     => $data['full_address'],
            'rt'               => $data['rt'] ?? '01',
            'rw'               => $data['rw'] ?? '01',
            'district'         => $data['district'] ?? null,
            'city'             => $data['city'] ?? null,
            'postal_code'      => $data['postal_code'] ?? null,
            'latitude'         => $data['latitude'] ?? null,
            'longitude'        => $data['longitude'] ?? null,
            'is_primary'       => $data['is_primary'] ?? 0
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }
}
