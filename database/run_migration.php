<?php
require_once __DIR__ . '/../app/Helpers/Env.php';
App\Helpers\Env::load(__DIR__ . '/../.env');
$cfg = require __DIR__ . '/../config/database.php';
try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}", $cfg['username'], $cfg['password'], $cfg['options']);
    $sql = file_get_contents(__DIR__ . '/migration_003_registration_system.sql');
    $pdo->exec($sql);
    echo "MIGRATION_003_SUCCESS\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Current tables: " . implode(', ', $tables) . "\n";
} catch (Exception $e) {
    echo "MIGRATION_ERROR: " . $e->getMessage() . "\n";
}
