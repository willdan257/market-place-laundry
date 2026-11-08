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

    echo "Running Migration 004...\n";
    $sql = file_get_contents(__DIR__ . '/migration_004_account_system_hardening.sql');
    $pdo->exec($sql);
    echo "  ✔ Migration 004 tables & columns checked/created.\n";

    // Clean up empty string phones to NULL so they don't violate UNIQUE index
    $pdo->exec("UPDATE users SET phone = NULL WHERE phone = ''");

    // Check if idx_users_phone already exists
    $idxStmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_phone'");
    $existingIndex = $idxStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingIndex) {
        // Normalize existing phone numbers before creating unique index
        echo "Normalizing existing phone numbers...\n";
        $users = $pdo->query("SELECT id, phone FROM users WHERE phone IS NOT NULL AND phone != ''")->fetchAll(PDO::FETCH_ASSOC);

        $seenPhones = [];
        $updateStmt = $pdo->prepare("UPDATE users SET phone = :phone WHERE id = :id");

        foreach ($users as $u) {
            $raw = trim($u['phone']);
            $cleaned = preg_replace('/[^\d+]/', '', $raw);
            if (str_starts_with($cleaned, '+62')) {
                $cleaned = '62' . substr($cleaned, 3);
            } elseif (str_starts_with($cleaned, '0')) {
                $cleaned = '62' . substr($cleaned, 1);
            } elseif (str_starts_with($cleaned, '8')) {
                $cleaned = '62' . $cleaned;
            }
            $normalized = preg_replace('/\D/', '', $cleaned);
            if (empty($normalized)) {
                $updateStmt->execute(['phone' => null, 'id' => $u['id']]);
                continue;
            }

            // Deduplication check: if duplicate found, keep first intact, suffix other row
            if (isset($seenPhones[$normalized])) {
                $safeUniquePhone = $normalized . '_dup' . $u['id'];
            } else {
                $safeUniquePhone = $normalized;
                $seenPhones[$normalized] = $u['id'];
            }

            if ($safeUniquePhone !== $raw) {
                $updateStmt->execute([
                    'phone' => $safeUniquePhone,
                    'id' => $u['id']
                ]);
            }
        }
        echo "  ✔ Existing phone numbers normalized and deduplicated safely.\n";

        $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX `idx_users_phone` (`phone`)");
        echo "  ✔ UNIQUE index idx_users_phone added successfully.\n";
    } else {
        echo "  ✔ UNIQUE index idx_users_phone already exists.\n";
    }

    echo "MIGRATION_004_COMPLETED_SUCCESSFULLY\n";
} catch (Exception $e) {
    echo "MIGRATION_004_ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
