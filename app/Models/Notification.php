<?php

namespace App\Models;

class Notification extends BaseModel
{
    protected string $table = 'notifications';

    public function getUserNotifications(int $userId): array
    {
        return $this->query("SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY id DESC", ['user_id' => $userId]);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->execute("UPDATE {$this->table} SET is_read = 1 WHERE id = :id AND user_id = :user_id", [
            'id' => $notificationId,
            'user_id' => $userId
        ]);
    }
}
