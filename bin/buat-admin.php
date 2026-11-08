<?php

/**
 * CLI Tool: Buat Akun Administrator Pertama
 * Marketplace Laundry
 *
 * Penggunaan:
 *   php bin/buat-admin.php
 */

if (php_sapi_name() !== 'cli') {
    echo "Skrip ini hanya dapat dijalankan melalui terminal (CLI).\n";
    exit(1);
}

// 1. Setup Autoloader & Environment
$rootDir = dirname(__DIR__);

spl_autoload_register(function ($class) use ($rootDir) {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $rootDir . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Helpers\Env;
use App\Helpers\Formatter;

$envPath = $rootDir . '/.env';
if (!file_exists($envPath)) {
    echo "\n[ERROR] File .env tidak ditemukan di $envPath\n";
    exit(1);
}
Env::load($envPath);

$dbConfig = require $rootDir . '/config/database.php';

try {
    $dsn = sprintf(
        "%s:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['driver'],
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset']
    );
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    echo "\n[ERROR] Gagal terhubung ke database: " . $e->getMessage() . "\n";
    exit(1);
}

echo "====================================================\n";
echo "   MARKETPLACE LAUNDRY - BUAT AKUN ADMINISTRATOR    \n";
echo "====================================================\n\n";

function promptInput(string $prompt, bool $isSecret = false): string
{
    echo $prompt;
    if ($isSecret && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Pada Windows CLI, fallback fgets normal agar tidak macet
        $input = trim((string) fgets(STDIN));
    } else {
        $input = trim((string) fgets(STDIN));
    }
    return $input;
}

// 1. Input Nama
do {
    $name = promptInput("Masukkan Nama Lengkap: ");
    if (empty($name)) {
        echo "[!] Nama tidak boleh kosong. Silakan ulangi.\n";
    }
} while (empty($name));

// 2. Input Email
do {
    $rawEmail = promptInput("Masukkan Alamat Email: ");
    $email = Formatter::normalizeEmail($rawEmail);
    if (empty($email) || !Formatter::isValidEmail($email)) {
        echo "[!] Format email tidak valid. Silakan ulangi (contoh: admin@example.com atau admin@test).\n";
        $emailValid = false;
        continue;
    }

    // Cek apakah email sudah terdaftar
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        echo "[!] Email '$email' sudah terdaftar dalam sistem. Gunakan email lain.\n";
        $emailValid = false;
        continue;
    }

    $emailValid = true;
} while (!$emailValid);

// 3. Input Nomor WhatsApp
do {
    $rawPhone = promptInput("Masukkan Nomor WhatsApp (08xx atau 628xx): ");
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

    if (!preg_match('/^(08|628)[0-9]{8,12}$/', $cleanPhone)) {
        echo "[!] Nomor WhatsApp tidak valid. Harus diawali 08xx atau 628xx dengan 10-15 digit angka.\n";
        $phoneValid = false;
        continue;
    }

    $normalizedPhone = Formatter::normalizePhone($cleanPhone);

    // Cek apakah nomor WA sudah terdaftar
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = :phone LIMIT 1");
    $stmt->execute([':phone' => $normalizedPhone]);
    if ($stmt->fetch()) {
        echo "[!] Nomor WhatsApp '$normalizedPhone' sudah terdaftar dalam sistem. Gunakan nomor lain.\n";
        $phoneValid = false;
        continue;
    }

    $phoneValid = true;
} while (!$phoneValid);

// 4. Input Password
do {
    $password = promptInput("Masukkan Password (bebas berapa karakter): ", true);
    if (strlen($password) < 1) {
        echo "[!] Password tidak boleh kosong.\n";
        $passValid = false;
        continue;
    }
    $passValid = true;
} while (!$passValid);

// 5. Eksekusi INSERT ke tabel users
try {
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO users (name, email, phone, password, role, status, is_active, email_verified_at, created_at, updated_at)
            VALUES (:name, :email, :phone, :password, 'admin', 'aktif', 1, NOW(), NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':phone'    => $normalizedPhone,
        ':password' => $passwordHash,
    ]);

    $newId = $pdo->lastInsertId();

    echo "\n----------------------------------------------------\n";
    echo "[SUKSES] Akun Administrator berhasil dibuat!\n";
    echo "- ID User  : " . $newId . "\n";
    echo "- Nama     : " . $name . "\n";
    echo "- Email    : " . $email . "\n";
    echo "- No. WA   : " . $normalizedPhone . "\n";
    echo "- Role     : admin\n";
    echo "- Status   : aktif\n";
    echo "----------------------------------------------------\n";
    echo "Silakan buka browser dan login lewat halaman Admin:\n";
    echo "URL: http://localhost:8000/admin/login\n\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Gagal menyimpan ke database: " . $e->getMessage() . "\n";
    exit(1);
}

