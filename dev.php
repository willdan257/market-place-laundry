<?php
/**
 * Single-command Development Runner - Marketplace Laundry
 * Menjalankan Chatbot (Node.js) & Web Server (PHP) secara bersamaan dalam 1 script.
 * 
 * Penggunaan:
 *   php dev.php
 */

$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$chatbotScript = __DIR__ . '/chatbot-server/server.js';

echo "==========================================\n";
echo "🚀 Menjalankan Marketplace Laundry & Chatbot\n";
echo "==========================================\n";

// Cek ketersediaan Node.js
$nodeVer = @shell_exec("node -v 2>&1");
if (!$nodeVer || !preg_match('/v\d+/i', $nodeVer)) {
    echo "⚠️  PERINGATAN: Node.js tidak ditemukan di PATH sistem!\n";
    echo "    Server chatbot (localhost:3001) tidak dapat dijalankan.\n";
    echo "    Silakan instal Node.js (>= 18) agar chatbot beranda aktif.\n\n";
} else {
    // Cek apakah port 3001 sudah dipakai
    $portCheck = @stream_socket_client("tcp://127.0.0.1:3001", $errno, $errstr, 0.5);
    if ($portCheck) {
        fclose($portCheck);
        echo "ℹ️  Port 3001 sudah digunakan (server chatbot mungkin sudah berjalan).\n";
    } else {
        // Jalankan Node.js Chatbot Server
        if ($isWin) {
            pclose(popen("start /B node \"" . $chatbotScript . "\"", "r"));
            $nodeProc = null;
        } else {
            $desc = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            $nodeProc = proc_open("node \"" . $chatbotScript . "\"", $desc, $pipes);
            
            // Otomatis matikan node saat script PHP berhenti (Ctrl+C)
            register_shutdown_function(function () use (&$nodeProc) {
                if (is_resource($nodeProc)) {
                    echo "\n🛑 Mematikan Chatbot Server...\n";
                    proc_terminate($nodeProc);
                    proc_close($nodeProc);
                }
            });
        }
    }
}

echo "🤖 Chatbot Server  : http://localhost:3001\n";
echo "🌐 Web Application : http://localhost:8000\n";
echo "------------------------------------------\n";
echo "💡 Tekan Ctrl + C untuk mematikan kedua server sekaligus.\n\n";

// Jalankan PHP Built-in Server
passthru("php -S 0.0.0.0:8000 -t public public/index.php");
