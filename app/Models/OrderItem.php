<?php

namespace App\Models;

class OrderItem extends BaseModel
{
    protected string $table = 'order_items';

    public function getItemsByOrderId(int $orderId): array
    {
        return $this->query("SELECT oi.*, s.service_name, s.unit FROM {$this->table} oi JOIN services s ON oi.service_id = s.id WHERE oi.order_id = :order_id", ['order_id' => $orderId]);
    }
}
