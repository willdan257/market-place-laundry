@echo off
title Marketplace Laundry ^& Chatbot
echo ==========================================
echo   Menjalankan Marketplace Laundry ^& Chatbot
echo ==========================================
echo.
echo [1/2] Menyalakan Chatbot Service di port 3001...
start "Chatbot Server (Port 3001)" cmd /k "cd chatbot-server && node server.js"

echo [2/2] Menyalakan Web Server PHP di port 8080...
echo.
echo Akses Web: http://localhost:8000
echo Tekan Ctrl + C untuk mematikan web server.
echo ==========================================
php -S localhost:8000 -t public public/index.php
