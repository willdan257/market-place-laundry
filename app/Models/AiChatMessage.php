<?php

namespace App\Models;

use PDO;
use Throwable;

class AiChatMessage extends BaseModel
{
    protected string $table = 'ai_chat_messages';
    private static bool $tableEnsured = false;

    /**
     * Pastikan tabel ai_chat_messages tersedia di database.
     */
    public static function ensureTableExists(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        try {
            $pdo = self::getPdo();
            if (!$pdo) {
                return;
            }

            $sql = "CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `user_name` VARCHAR(150) NULL,
                `user_email` VARCHAR(150) NULL,
                `user_role` VARCHAR(50) NOT NULL DEFAULT 'customer',
                `order_id` BIGINT UNSIGNED NULL,
                `message` TEXT NOT NULL,
                `response` MEDIUMTEXT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_ai_chat_user` (`user_id`),
                INDEX `idx_ai_chat_role` (`user_role`),
                INDEX `idx_ai_chat_order` (`order_id`),
                INDEX `idx_ai_chat_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            $pdo->exec($sql);
            self::$tableEnsured = true;
        } catch (Throwable $e) {
            error_log('[AiChatMessage::ensureTableExists] Error: ' . $e->getMessage());
        }
    }

    /**
     * Catat interaksi tanya-jawab AI ke database.
     */
    public static function recordMessage(
        ?int $userId,
        ?string $userName,
        ?string $userEmail,
        string $userRole,
        ?int $orderId,
        string $message,
        string $response
    ): bool {
        self::ensureTableExists();

        try {
            $pdo = self::getPdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare("
                INSERT INTO `ai_chat_messages` 
                    (`user_id`, `user_name`, `user_email`, `user_role`, `order_id`, `message`, `response`, `created_at`)
                VALUES 
                    (:user_id, :user_name, :user_email, :user_role, :order_id, :message, :response, NOW())
            ");

            return $stmt->execute([
                'user_id'    => $userId > 0 ? $userId : null,
                'user_name'  => !empty($userName) ? trim($userName) : 'Tamu',
                'user_email' => !empty($userEmail) ? trim($userEmail) : null,
                'user_role'  => !empty($userRole) ? trim($userRole) : 'customer',
                'order_id'   => $orderId > 0 ? $orderId : null,
                'message'    => trim($message),
                'response'   => trim($response),
            ]);
        } catch (Throwable $e) {
            error_log('[AiChatMessage::recordMessage] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil riwayat chat AI dengan pagination dan filter pencarian.
     */
    public function getAiConversations(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        self::ensureTableExists();

        $pdo = self::getPdo();
        if (!$pdo) {
            return [
                'items' => [],
                'pagination' => [
                    'page'        => 1,
                    'per_page'    => $perPage,
                    'total'       => 0,
                    'total_pages' => 1,
                ]
            ];
        }

        $conditions = ["1=1"];
        $params = [];

        // 1. Filter Role
        $role = trim((string) ($filters['role'] ?? ''));
        if (!empty($role)) {
            // Normalisasi alias role
            $canonicalRole = match (strtolower($role)) {
                'customer', 'pelanggan' => 'customer',
                'owner', 'mitra'        => 'owner',
                'driver', 'kurir'       => 'driver',
                'admin'                 => 'admin',
                'tamu', 'guest'         => 'tamu',
                default                 => $role,
            };
            $conditions[] = "m.user_role = :role";
            $params['role'] = $canonicalRole;
        }

        // 2. Filter Search (Nama pengguna, email, teks pesan, jawaban AI, atau nomor order)
        $search = trim((string) ($filters['search'] ?? ''));
        if (!empty($search)) {
            $conditions[] = "(
                m.user_name LIKE :search 
                OR m.user_email LIKE :search 
                OR m.message LIKE :search 
                OR m.response LIKE :search 
                OR o.order_number LIKE :search
                OR u.name LIKE :search
                OR u.email LIKE :search
            )";
            $params['search'] = "%{$search}%";
        }

        // 3. Filter Tanggal Mulai
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if (!empty($startDate)) {
            $conditions[] = "m.created_at >= :start_date";
            $params['start_date'] = "{$startDate} 00:00:00";
        }

        // 4. Filter Tanggal Akhir
        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if (!empty($endDate)) {
            $conditions[] = "m.created_at <= :end_date";
            $params['end_date'] = "{$endDate} 23:59:59";
        }

        $whereClause = implode(" AND ", $conditions);

        // Hitung total data
        $countSql = "
            SELECT COUNT(*) as total 
            FROM `ai_chat_messages` m
            LEFT JOIN `users` u ON u.id = m.user_id
            LEFT JOIN `laundry_orders` o ON o.id = m.order_id
            WHERE {$whereClause}
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) ($stmtCount->fetchColumn() ?: 0);

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        $offset = ($page - 1) * $perPage;

        // Ambil data pesan dengan informasi pengguna dan pesanan terkait
        $sql = "
            SELECT 
                m.id,
                m.user_id,
                COALESCE(u.name, m.user_name, 'Tamu') as user_name,
                COALESCE(u.email, m.user_email, '') as user_email,
                COALESCE(u.phone, '') as user_phone,
                m.user_role,
                m.order_id,
                o.order_number,
                m.message,
                m.response,
                m.created_at
            FROM `ai_chat_messages` m
            LEFT JOIN `users` u ON u.id = m.user_id
            LEFT JOIN `laundry_orders` o ON o.id = m.order_id
            WHERE {$whereClause}
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ]
        ];
    }

    /**
     * Hitung total semua pesan AI yang tersimpan.
     */
    public static function countTotal(): int
    {
        self::ensureTableExists();

        try {
            $pdo = self::getPdo();
            if (!$pdo) {
                return 0;
            }
            $stmt = $pdo->query("SELECT COUNT(*) FROM `ai_chat_messages`");
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

