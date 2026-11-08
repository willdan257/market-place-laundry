#!/usr/bin/env bash

# Pastikan berpindah ke direktori script ini
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

# Tangkap Ctrl+C untuk mematikan kedua proses sekaligus
cleanup() {
    echo ""
    echo "🛑 Menghentikan semua server..."
    kill $CHATBOT_PID 2>/dev/null
    kill $PHP_PID 2>/dev/null
    exit 0
}
trap cleanup SIGINT SIGTERM EXIT

echo "=========================================="
echo "🚀 Menjalankan Marketplace Laundry & Chatbot"
echo "=========================================="

# 1. Jalankan Chatbot Node.js di background
node chatbot-server/server.js &
CHATBOT_PID=$!

# 2. Jalankan PHP Built-in Server di background
php -S 0.0.0.0:8080 -t public public/index.php &
PHP_PID=$!

echo "🤖 Chatbot Server  : http://localhost:3001"
echo "🌐 Web Application : http://localhost:8080"
echo "------------------------------------------"
echo "💡 Tekan Ctrl + C untuk mematikan kedua server sekaligus."
echo "=========================================="

# Tunggu proses berjalan
wait
