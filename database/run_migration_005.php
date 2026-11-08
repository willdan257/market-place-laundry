<?php

require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');
$cfg = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}",
        $cfg['username'],
        $cfg['password'],
        $cfg['options']
    );

    echo "Running Migration 005 (Chat System)...\n";
    $sql = file_get_contents(__DIR__ . '/migration_005_chat_system.sql');
    $pdo->exec($sql);
    echo "  ✔ Table `order_chats` checked/created successfully.\n";

    // Cek apakah sudah ada data percakapan dummy
    $count = (int) $pdo->query("SELECT COUNT(*) FROM order_chats")->fetchColumn();
    if ($count === 0) {
        echo "Seeding initial order chats...\n";
        // Ambil beberapa order yang ada
        $orders = $pdo->query("
            SELECT o.id, o.customer_id, s.owner_id, d.user_id as driver_user_id
            FROM laundry_orders o
            JOIN stores s ON s.id = o.store_id
            LEFT JOIN drivers d ON d.id = COALESCE(o.pickup_driver_id, o.delivery_driver_id)
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtInsert = $pdo->prepare("
            INSERT INTO order_chats (order_id, sender_id, sender_role, message, created_at)
            VALUES (:order_id, :sender_id, :sender_role, :message, :created_at)
        ");

        $now = time();
        foreach ($orders as $idx => $ord) {
            $orderId = (int) $ord['id'];
            $custId = (int) $ord['customer_id'];
            $ownerId = (int) $ord['owner_id'];
            $driverId = !empty($ord['driver_user_id']) ? (int) $ord['driver_user_id'] : null;

            // Chat 1: Customer tanya
            $t1 = date('Y-m-d H:i:s', $now - 7200 + ($idx * 1200));
            $stmtInsert->execute([
                'order_id'    => $orderId,
                'sender_id'   => $custId,
                'sender_role' => 'customer',
                'message'     => 'Halo kak, apakah cucian saya sudah selesai dikeringkan?',
                'created_at'  => $t1,
            ]);

            // Chat 2: Owner balas
            $t2 = date('Y-m-d H:i:s', $now - 6000 + ($idx * 1200));
            $stmtInsert->execute([
                'order_id'    => $orderId,
                'sender_id'   => $ownerId,
                'sender_role' => 'owner',
                'message'     => 'Halo kak! Sudah masuk proses setrika uap ya, estimasi 1 jam lagi siap diantar.',
                'created_at'  => $t2,
            ]);

            // Chat 3: Driver (jika ada)
            if ($driverId) {
                $t3 = date('Y-m-d H:i:s', $now - 3600 + ($idx * 1200));
                $stmtInsert->execute([
                    'order_id'    => $orderId,
                    'sender_id'   => $driverId,
                    'sender_role' => 'driver',
                    'message'     => 'Siap, saya sedang menuju lokasi toko untuk ambil paketnya.',
                    'created_at'  => $t3,
                ]);
            }
        }
        echo "  ✔ Seeded sample chat messages successfully.\n";
    }

} catch (PDOException $e) {
    echo "  ✘ Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}

