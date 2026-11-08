<?php

/**
 * Marketplace Laundry - Front Controller
 */

// Route static assets directly when using PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (str_starts_with($reqPath, '/admin/uploads/')) {
        $cleanUploadPath = substr($reqPath, 6); // remove '/admin'
        if (is_file(__DIR__ . $cleanUploadPath)) {
            header("Location: " . $cleanUploadPath, true, 302);
            exit;
        }
    }
    if ($reqPath !== '/' && is_file(__DIR__ . $reqPath)) {
        return false;
    }
}

// 1. PSR-4 Autoloader Implementation for Native PHP Setup
spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => __DIR__ . '/../app/',
        'Routes\\' => __DIR__ . '/../routes/'
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// 2. Load Environment Variables
use App\Helpers\Env;
use App\Helpers\Session;

Env::load(__DIR__ . '/../.env');

// 3. Set Timezone & Error Reporting
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Jakarta');

$isProduction = ($appConfig['env'] ?? 'production') === 'production';
$isDebug = (bool) ($appConfig['debug'] ?? false);

// In production, debug MUST be forced to false and display_errors turned OFF
if ($isProduction) {
    $isDebug = false;
    error_reporting(0);
    ini_set('display_errors', '0');
} elseif ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// 4. Initialize Session (Secure params handled in Session::start)
Session::start();

// 5. Send Hardened Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com https://maps.googleapis.com https://app.sandbox.midtrans.com https://app.midtrans.com https://www.svgrepo.com; img-src 'self' data: https: blob:; connect-src 'self' https://maps.googleapis.com https://api.midtrans.com https://app.sandbox.midtrans.com https://app.midtrans.com http://localhost:3001;");

if ($isProduction && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// 6. Load Web Routes & Dispatch Request if running via Web HTTP
$router = require __DIR__ . '/../routes/web.php';
if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])) {
    $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
}
