<?php

namespace App\Models;

use PDO;
use Exception;

class OrderChat extends BaseModel
{
    protected string $table = 'order_chats';

    /**
     * Menyimpan satu pesan ke riwayat chat pesanan.
     */
    public function saveMessage(int $orderId, int $senderId, string $senderRole, string $message): int|false
    {
        require_once __DIR__ . '/../Helpers/chat_log.php';
        return simpan_chat($orderId, $senderId, $senderRole, $message);
    }

    /**
     * Mengambil daftar percakapan teragregasi per pesanan dengan filter & pagination.
     *
     * @param array $filters ['role' => string, 'search' => string, 'start_date' => string, 'end_date' => string]
     * @param int $page Halaman aktif (mulai dari 1)
     * @param int $perPage Jumlah percakapan per halaman (default 20)
     * @return array{items: array, pagination: array}
     */
    public function getConversations(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0]];
        }

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $whereConditions = [];
        $params = [];

        // Filter role pengirim
        if (!empty($filters['role'])) {
            $roleFilter = strtolower(trim($filters['role']));
            $canonicalRole = match ($roleFilter) {
                'admin'                 => 'admin',
                'owner', 'mitra'        => 'owner',
                'driver', 'kurir'       => 'driver',
                'customer', 'pelanggan' => 'customer',
                default                 => $roleFilter,
            };
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM order_chats c_role 
                WHERE c_role.order_id = o.id AND c_role.sender_role = :role
            )";
            $params['role'] = $canonicalRole;
        }

        // Filter rentang tanggal
        if (!empty($filters['start_date'])) {
            $whereConditions[] = "c.created_at >= :start_date";
            $params['start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $whereConditions[] = "c.created_at <= :end_date";
            $params['end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        // Filter pencarian teks (nomor order, nama pengirim, isi pesan)
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $whereConditions[] = "(
                o.order_number LIKE :search_order 
                OR EXISTS (
                    SELECT 1 FROM order_chats c_search 
                    JOIN users u_search ON u_search.id = c_search.sender_id
                    WHERE c_search.order_id = o.id 
                      AND (u_search.name LIKE :search_name OR c_search.message LIKE :search_msg)
                )
            )";
            $params['search_order'] = $search;
            $params['search_name'] = $search;
            $params['search_msg'] = $search;
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        // Hitung total percakapan unik (distinct order_id)
        $countSql = "
            SELECT COUNT(DISTINCT o.id) 
            FROM order_chats c
            JOIN laundry_orders o ON o.id = c.order_id
            JOIN users u ON u.id = c.sender_id
            {$whereClause}
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 1;

        // Query data percakapan
        $dataSql = "
            SELECT 
                o.id as order_id,
                o.order_number,
                o.order_status,
                s.store_name,
                COUNT(c.id) as message_count,
                MAX(c.created_at) as last_message_at,
                (SELECT c2.message FROM order_chats c2 WHERE c2.order_id = o.id ORDER BY c2.created_at DESC, c2.id DESC LIMIT 1) as last_message,
                (SELECT u2.name FROM order_chats c2 JOIN users u2 ON u2.id = c2.sender_id WHERE c2.order_id = o.id ORDER BY c2.created_at DESC, c2.id DESC LIMIT 1) as last_sender_name,
                (SELECT c2.sender_role FROM order_chats c2 WHERE c2.order_id = o.id ORDER BY c2.created_at DESC, c2.id DESC LIMIT 1) as last_sender_role,
                GROUP_CONCAT(DISTINCT CONCAT(u.name, ':::', c.sender_role) ORDER BY c.created_at SEPARATOR '|||') as participants_raw
            FROM order_chats c
            JOIN laundry_orders o ON o.id = c.order_id
            JOIN stores s ON s.id = o.store_id
            JOIN users u ON u.id = c.sender_id
            {$whereClause}
            GROUP BY o.id, o.order_number, o.order_status, s.store_name
            ORDER BY last_message_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmtData = $pdo->prepare($dataSql);
        foreach ($params as $k => $v) {
            $stmtData->bindValue($k, $v);
        }
        $stmtData->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->execute();

        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        // Format peserta percakapan
        $items = [];
        foreach ($rows as $r) {
            $participants = [];
            if (!empty($r['participants_raw'])) {
                $parts = explode('|||', $r['participants_raw']);
                foreach ($parts as $p) {
                    $pair = explode(':::', $p, 2);
                    if (count($pair) === 2) {
                        $participants[] = [
                            'name' => $pair[0],
                            'role' => $pair[1]
                        ];
                    }
                }
            }

            $items[] = [
                'order_id'          => (int) $r['order_id'],
                'order_number'      => $r['order_number'],
                'order_status'      => $r['order_status'],
                'store_name'        => $r['store_name'],
                'message_count'     => (int) $r['message_count'],
                'last_message_at'   => $r['last_message_at'],
                'last_message'      => $r['last_message'] ?? '',
                'last_sender_name'  => $r['last_sender_name'] ?? 'Pengguna',
                'last_sender_role'  => $r['last_sender_role'] ?? 'customer',
                'participants'      => $participants,
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'total'       => $totalItems,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ]
        ];
    }

    /**
     * Mengambil detail percakapan lengkap untuk satu pesanan.
     */
    public function getConversationDetail(int $orderId): ?array
    {
        $pdo = self::getPdo();
        if (!$pdo || $orderId <= 0) {
            return null;
        }

        // Ambil info order & store & customer
        $stmtOrder = $pdo->prepare("
            SELECT 
                o.id as order_id,
                o.order_number,
                o.order_status,
                o.total_amount,
                o.created_at as order_created_at,
                s.store_name,
                s.phone as store_phone,
                u_cust.name as customer_name,
                u_cust.phone as customer_phone
            FROM laundry_orders o
            JOIN stores s ON s.id = o.store_id
            JOIN users u_cust ON u_cust.id = o.customer_id
            WHERE o.id = :order_id
            LIMIT 1
        ");
        $stmtOrder->execute(['order_id' => $orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // Ambil seluruh pesan berurutan waktu kronologis
        $stmtMessages = $pdo->prepare("
            SELECT 
                c.id,
                c.order_id,
                c.sender_id,
                c.sender_role,
                c.message,
                c.created_at,
                u.name as sender_name,
                u.email as sender_email,
                u.phone as sender_phone
            FROM order_chats c
            JOIN users u ON u.id = c.sender_id
            WHERE c.order_id = :order_id
            ORDER BY c.created_at ASC, c.id ASC
        ");
        $stmtMessages->execute(['order_id' => $orderId]);
        $messages = $stmtMessages->fetchAll(PDO::FETCH_ASSOC);

        return [
            'order'    => $order,
            'messages' => $messages,
        ];
    }
}

