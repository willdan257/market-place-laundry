<?php

namespace App\Models;

class PartnerService extends BaseModel
{
    protected string $table = 'partner_services';

    public function getByPartnerId(int $partnerId): array
    {
        return $this->query("SELECT * FROM {$this->table} WHERE partner_id = :partner_id ORDER BY id ASC", [
            'partner_id' => $partnerId
        ]);
    }

    public function addService(int $partnerId, string $namaLayanan, string $tipe, float $harga): int|bool
    {
        $sql = "INSERT INTO {$this->table} (partner_id, nama_layanan, tipe, harga)
                VALUES (:partner_id, :nama_layanan, :tipe, :harga)";

        $success = $this->execute($sql, [
            'partner_id'   => $partnerId,
            'nama_layanan' => $namaLayanan,
            'tipe'         => $tipe,
            'harga'        => $harga
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }

    public function batchAddServices(int $partnerId, array $services): bool
    {
        foreach ($services as $srv) {
            if (!empty($srv['nama_layanan']) && isset($srv['harga'])) {
                $this->addService(
                    $partnerId,
                    (string) $srv['nama_layanan'],
                    (string) ($srv['tipe'] ?? 'kiloan'),
                    (float) $srv['harga']
                );
            }
        }
        return true;
    }
}
