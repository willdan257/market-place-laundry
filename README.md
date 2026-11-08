# 🧺 Marketplace Laundry Platform

Platform web **Marketplace Laundry** berbasis arsitektur **MVC (Model-View-Controller) Native PHP 8** dengan integrasi Google OAuth, Google Maps API, Midtrans Payment Gateway (QRIS & Cash), AI Customer Assistant, serta sistem penugasan kurir real-time.

---

## 🌟 Fitur Utama

- **Multi-Role Authentication & RBAC**: Hak akses terisolasi untuk **Customer**, **Owner Toko Laundry**, dan **Kurir (Driver)**.
- **Google OAuth Login**: Autentikasi aman tanpa kata sandi dengan verifikasi CSRF state.
- **Smart Address & Geolocation**: Menampung alamat lengkap, nomor telepon, RT, RW, serta titik koordinat GPS (Latitude & Longitude).
- **Automated Distance & Delivery Fee**: Perhitungan rute berkendara dan tarif ongkir dinamis menggunakan **Google Maps Distance Matrix API** (didukung fallback cerdas algoritma Haversine untuk pengujian lokal).
- **Live Courier Tracking**: Status perjalanan kurir transparan (`driver_on_the_way` → `arrived_at_customer` → `picked_up` → `arrived_at_store` → `delivered`).
- **Owner Penimbangan & Invoice**: Pemilik toko menginput berat aktual cucian, dan sistem otomatis meregenerasi total tagihan.
- **Dual Payment Options (QRIS & CASH)**:
  - **QRIS Dinamis (Midtrans)**: Countdown otomatis 60 menit, verifikasi signature webhook SHA-512, dan lock database atomik (`FOR UPDATE`).
  - **Bayar Tunai (CASH)**: Pelanggan membayar saat pengantaran, diverifikasi langsung oleh Owner.
- **Payment Expiration & Auto-Cancellation**: Pembayaran yang tidak diselesaikan dalam 1 jam otomatis dibatalkan, status order menjadi `cancelled`, dan notifikasi dikirimkan ke pelanggan.
- **Multi-Role AI Assistant**: Asisten AI cerdas multi-peran (Gemini / Anthropic / Nous / Groq) terhubung langsung ke database dengan peran otomatis sesuai akun login:
  - **Owner**: Asisten manajer toko (ringkasan pesanan, cucian perlu ditimbang, konfirmasi bayar tunai, omzet, status kurir).
  - **Customer**: CS laundry ramah (status pesanan, daftar layanan, auto-generate & kartu pembayaran QRIS).
  - **Driver**: Asisten kurir to-the-point (tugas aktif, alamat detail pelanggan, tombol navigasi rute Google Maps, kontak).
  - **Tamu (Guest)**: Informasi publik & jam operasional tanpa akses database pesanan privat.
- **Responsive Modern UI**: Dibangun dengan Bootstrap 5, Bootstrap Icons, Google Fonts *Plus Jakarta Sans*, micro-animations, dan tata letak mobile-friendly.

---

## 💻 Requirements

Sebelum menginstal, pastikan komputer/server Anda memenuhi syarat minimum:
- **PHP**: Versi `>= 8.0` (Direkomendasikan PHP 8.1 / 8.2) dengan ekstensi aktif:
  - `pdo` & `pdo_mysql`
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`
- **Database**: MySQL `>= 8.0` atau MariaDB `>= 10.4` (tersedia via XAMPP).
- **Web Server**: PHP Built-in Server (CLI) atau Apache Web Server (XAMPP).
- **Node.js** (Opsional): Node.js `>= 18.0` jika ingin menjalankan server chatbot beranda (`chatbot-server`).

---

## 🚀 Installation

1. **Clone / Salin Repository ke direktori server**:
   Jika menggunakan XAMPP di Windows:
   ```bash
   cd c:\xampp\htdocs
   # atau letakkan folder di c:\xampp\htdocs\marketplace-laundry
   ```

2. **Salin Template Konfigurasi Environment**:
   Salin file `.env.example` menjadi `.env`:
   ```bash
   cp .env.example .env
   # Pada Windows Command Prompt / Powershell:
   copy .env.example .env
   ```

3. **Pastikan Folder Storage Memiliki Izin Tulis**:
   Pastikan direktori cache dan logs dapat ditulis oleh PHP:
   - `storage/cache/`
   - `storage/logs/`

---

## ⚙️ Environment Variables

Buka file `.env` dan sesuaikan parameter berikut:

```ini
APP_NAME="Marketplace Laundry"
APP_ENV=local               # 'local' untuk development, 'production' untuk server live
APP_DEBUG=true              # 'true' untuk menampilkan error detail di development
APP_URL=http://localhost:8000

# ------------------------------------------
# Database Configuration
# ------------------------------------------
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace_laundry
DB_USERNAME=root
DB_PASSWORD=

# ------------------------------------------
# Google OAuth 2.0 Credentials
# ------------------------------------------
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

# ------------------------------------------
# Google Maps API Configuration
# ------------------------------------------
GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here

# ------------------------------------------
# Midtrans Payment Gateway Configuration
# ------------------------------------------
PAYMENT_ENV=sandbox          # 'sandbox' atau 'production'
PAYMENT_SERVER_KEY=your_payment_server_key_here
PAYMENT_CLIENT_KEY=your_payment_client_key_here

# ------------------------------------------
# AI Customer Assistant Configuration
# ------------------------------------------
AI_PROVIDER=gemini           # 'gemini' atau 'anthropic' atau 'nous'
AI_API_KEY=your_ai_api_key_here
AI_MODEL=                    # Kosongkan untuk model default, atau tentukan spesifik
AI_RATE_LIMIT_PER_MINUTE=8
AI_RATE_LIMIT_PER_DAY=100
AI_GUEST_RATE_LIMIT_PER_MINUTE=5
AI_GUEST_RATE_LIMIT_PER_DAY=30
```

---

## 🗄️ Database Setup

1. Buka MySQL client melalui CLI atau **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Buat database dan import skema dasar:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
3. Jika menggunakan database yang sudah ada sebelumnya, jalankan migrasi modul pembayaran:
   ```bash
   mysql -u root -p < database/migration_002_payment_module.sql
   ```
4. Seluruh 10 tabel utama akan terbuat:
   - `users`, `customer_addresses`, `stores`, `services`, `drivers`
   - `laundry_orders`, `order_items`, `payments`
   - `order_status_histories`, `notifications`

---

## 🔑 Google OAuth Setup (Produksi)

1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat project baru atau pilih project yang sudah ada.
3. Buka menu **APIs & Services** → **OAuth consent screen**:
   - Pilih jenis User: **External**.
   - Masukkan informasi aplikasi (App name: `Marketplace Laundry`, Developer contact email).
   - Tambahkan scopes resmi: `openid`, `email`, `profile`.
4. Buka tab **Credentials** → **Create Credentials** → **OAuth client ID**:
   - Application Type: **Web application**.
   - Name: `Marketplace Laundry Web Client`.
   - Authorized JavaScript origins: Masukkan URL domain Anda (contoh: `http://localhost:8000` atau `https://your-domain.com`).
   - Authorized redirect URIs: `{APP_URL}/auth/google/callback` (contoh: `http://localhost:8000/auth/google/callback`).
5. Salin kredensial asli yang dihasilkan ke file `.env` server Anda (kredensial asli tidak boleh dicatat dalam source code):
   ```ini
   GOOGLE_CLIENT_ID=xxxxxxxxxxxx-xxxxxxxxxxxxxxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   ```
> **Catatan Produksi:** Seluruh rute simulasi login demo telah dihapus total. Jika `GOOGLE_CLIENT_ID` atau `GOOGLE_CLIENT_SECRET` belum diisi, tombol Google akan otomatis dinonaktifkan dengan pesan informasi yang aman tanpa menimbulkan celah pembuatan akun palsu.

---

## 🛡️ Pembuatan Akun Administrator Pertama (CLI)

Untuk alasan keamanan produksi, registrasi admin publik ditiadakan. Gunakan script CLI interaktif berikut dari server terminal:

```bash
php database/create_admin.php
```

Script akan meminta input nama, email unik, nomor telepon WhatsApp, dan password dengan konfirmasi hash bcrypt yang aman. Akun yang dibuat dapat langsung login di `/login` dan mengakses konsol `/admin/verifikasi`.

---

## 🗺️ Google Maps Setup

1. Di Google Cloud Console, aktifkan:
   - **Distance Matrix API**
   - **Maps JavaScript API** (untuk peta interaktif)
2. Buat API Key baru di menu **Credentials** → **Create API Key**.
3. Batasi key (API Restrictions) ke *Distance Matrix API* dan *Maps JavaScript API*.
4. Salin key ke `.env`:
   ```ini
   GOOGLE_MAPS_API_KEY=AIzaSyxxxxxxxxxxxxxxxxx
   ```
> **Smart Fallback:** Jika `GOOGLE_MAPS_API_KEY` belum diisi saat development lokal, sistem otomatis menggunakan formula matematika **Haversine (dengan faktor kelokan jalan 1.3x)** sehingga kalkulasi jarak dan ongkir tetap berfungsi 100% tanpa error!

---

## 💳 Payment Gateway Setup (Midtrans)

1. Daftar akun sandbox di [Midtrans Sandbox](https://dashboard.sandbox.midtrans.com/).
2. Buka **Settings** → **Access Keys**:
   - Salin **Server Key** dan **Client Key**.
   - Masukkan ke `.env`:
     ```ini
     PAYMENT_ENV=sandbox
     PAYMENT_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxx
     PAYMENT_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxx
     ```
3. Buka **Settings** → **Configuration**:
   - Payment Notification URL (Webhook): Masukkan URL endpoint webhook Anda:
     `https://your-domain.com/payment/webhook/midtrans`
     *(Untuk pengujian lokal, Anda dapat menggunakan tunneling ngrok)*.
4. **Keamanan Webhook**:
   - Midtrans mengirimkan signature SHA-512 dengan rumus: `hash('sha512', order_id + status_code + gross_amount + server_key)`.
   - Sistem secara otomatis memverifikasi signature ini sebelum memproses status pembayaran dan mencegah serangan manipulasi harga (*underpayment attack*).

---

## 🤖 AI API Setup

Aplikasi menyediakan asisten AI cerdas untuk pelanggan:
1. **Google Gemini (Default)**:
   - Dapatkan API Key di [Google AI Studio](https://aistudio.google.com/).
   - Set `.env`:
     ```ini
     AI_PROVIDER=gemini
     AI_API_KEY=AIzaSyxxxxxxxxxxxxxxxxx
     ```
2. **Anthropic Claude**:
   - Dapatkan API Key di [Anthropic Console](https://console.anthropic.com/).
   - Set `.env`:
     ```ini
     AI_PROVIDER=anthropic
     AI_API_KEY=sk-ant-xxxxxxxxxxxxxxxxx
     ```
3. **Nous / Claude via OpenAI-compatible Proxy**:
   - Set `.env`:
     ```ini
     AI_PROVIDER=nous
     AI_API_KEY=sk-xxxxxxxxxxxxxxxxx
     AI_MODEL=anthropic/claude-haiku-5.5
     ```

---

## 🖥️ Cara Menjalankan Aplikasi

### Opsi 1: Menggunakan Script Runner Bersama Chatbot (Direkomendasikan)
Menjalankan Web Server PHP (port 8000) dan Chatbot Node.js (port 3001) secara simultan:
```bash
php dev.php
```
Buka browser: `http://localhost:8000`

### Opsi 2: Menggunakan PHP Built-in Server Saja
```bash
php -S localhost:8000 -t public public/index.php
```
Buka browser: `http://localhost:8000`

### Opsi 3: Menggunakan Apache XAMPP
Pastikan folder berada di `c:\xampp\htdocs\marketplace-laundry`, nyalakan Apache dan MySQL di XAMPP Control Panel, lalu buka:
`http://localhost/marketplace-laundry`

---

## 🧪 Cara Menjalankan Test

Aplikasi dilengkapi dengan test suite integrasi otomatis yang menguji seluruh siklus end-to-end, alur QRIS, alur CASH, masa kadaluarsa pembayaran, otorisasi RBAC, hingga penanganan race condition webhook:

```bash
# Menggunakan PHP CLI
php tests/run_tests.php

# Atau melalui Composer
composer test

# Atau melalui npm
npm test
```

Dokumentasi detail pengujian dapat dibaca di [TESTING.md](file:///c:/xampp/htdocs/marketplace-laundry/TESTING.md).

---

## 📚 Dokumentasi Terkait

- [TESTING.md](file:///c:/xampp/htdocs/marketplace-laundry/TESTING.md) — Rincian skenario pengujian & hasil test runner.
- [API_ENDPOINTS.md](file:///c:/xampp/htdocs/marketplace-laundry/API_ENDPOINTS.md) — Referensi lengkap rute & endpoint HTTP.
- [DATABASE.md](file:///c:/xampp/htdocs/marketplace-laundry/DATABASE.md) — Arsitektur ERD & DDL Database.
- [SECURITY_AUDIT.md](file:///c:/xampp/htdocs/marketplace-laundry/SECURITY_AUDIT.md) — Laporan audit keamanan, mitigasi race conditions, dan RBAC.
