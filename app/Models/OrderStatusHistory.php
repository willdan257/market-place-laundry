<?php

namespace App\Models;

class OrderStatusHistory extends BaseModel
{
    protected string $table = 'order_status_histories';

    public function recordHistory(int $orderId, ?string $previousStatus, string $newStatus, ?string $notes = null, ?int $updatedBy = null): bool
    {
        $sql = "INSERT INTO {$this->table} (order_id, previous_status, new_status, notes, updated_by)
                VALUES (:order_id, :previous_status, :new_status, :notes, :updated_by)";

        return $this->execute($sql, [
            'order_id' => $orderId,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'updated_by' => $updatedBy
        ]);
    }

    public function getHistoryByOrderId(int $orderId): array
    {
        return $this->query(
            "SELECT h.*, u.name as updated_by_name, u.role as updated_by_role 
             FROM {$this->table} h 
             LEFT JOIN users u ON h.updated_by = u.id 
             WHERE h.order_id = :order_id 
             ORDER BY h.id ASC",
            ['order_id' => $orderId]
        );
    }
}
