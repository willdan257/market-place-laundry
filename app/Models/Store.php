<?php

namespace App\Models;

class Store extends BaseModel
{
    protected string $table = 'stores';

    public function findByOwnerId(int $ownerId): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE owner_id = :owner_id LIMIT 1", ['owner_id' => $ownerId]);
        return $results[0] ?? null;
    }

    /**
     * Mengambil semua toko yang buka (is_open = 1) dan mitranya berstatus disetujui
     * dengan agregasi jumlah layanan aktif dan harga mulai dari.
     */
    public function getOpenApprovedStores(): array
    {
        return $this->query("
            SELECT 
                st.*,
                p.nama_usaha,
                p.jam_operasional,
                COUNT(s.id) AS service_count,
                MIN(s.price_per_unit) AS min_price
            FROM {$this->table} st
            JOIN partners p ON st.owner_id = p.user_id
            LEFT JOIN services s ON s.store_id = st.id AND s.is_active = 1
            WHERE st.is_open = 1 AND p.status_verifikasi = 'disetujui'
            GROUP BY st.id
            ORDER BY st.id ASC
        ");
    }

    /**
     * Mencari toko tertentu yang buka dan berstatus disetujui.
     */
    public function findOpenApprovedStore(int $storeId): ?array
    {
        $results = $this->query("
            SELECT st.*, p.nama_usaha, p.jam_operasional
            FROM {$this->table} st
            JOIN partners p ON st.owner_id = p.user_id
            WHERE st.id = :id AND st.is_open = 1 AND p.status_verifikasi = 'disetujui'
            LIMIT 1
        ", ['id' => $storeId]);

        return $results[0] ?? null;
    }

    /**
     * Update status buka/tutup toko (1 = Buka, 0 = Tutup).
     */
    public function updateOpenStatus(int $storeId, int $isOpen): bool
    {
        return $this->execute("UPDATE {$this->table} SET is_open = :is_open, updated_at = NOW() WHERE id = :id", [
            'is_open' => $isOpen ? 1 : 0,
            'id'      => $storeId,
        ]);
    }
}
