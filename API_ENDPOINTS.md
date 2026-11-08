# 📡 API & ROUTE ENDPOINTS DOCUMENTATION

Aplikasi **Marketplace Laundry** menggunakan Front Controller Pattern yang dikelola oleh custom routing engine (`Routes\Router`). Seluruh endpoint dilindungi oleh lapisan middleware berbasis autentikasi, otorisasi peran (*Role-Based Access Control*), dan kelengkapan profil.

---

## 📑 Daftar Kategori Endpoint

1. [Public & Authentication](#1-public--authentication)
2. [Profile & Onboarding](#2-profile--onboarding)
3. [Dashboard Router](#3-dashboard-routes)
4. [Customer Order Management](#4-customer-order-management)
5. [AI Customer Assistant](#5-ai-customer-assistant)
6. [Owner Store Management](#6-owner-store-management)
7. [Driver Trip Management](#7-driver-trip-management)
8. [Payment Gateway & Webhook](#8-payment-gateway--webhook)

---

## 1. Public & Authentication

| Method | Endpoint | Guards | Deskripsi |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | *None* | Halaman Landing Page publik. |
| `GET` | `/login`, `/masuk` | *None* | Formulir masuk akun via Email / Nomor HP + Password atau Google OAuth asli. |
| `POST` | `/login` | RateLimiter, CSRF | Memproses login identitas + password dengan rate limit IP & identitas (5x / 15 menit). |
| `POST` | `/logout` | `Auth`, CSRF | Menghancurkan sesi pengguna aktif secara aman via POST dan me-regenerate token sesi. |
| `GET` | `/auth/google/redirect` | *None* | Redirect browser ke Google OAuth 2.0 consent screen resmi (`openid email profile`). |
| `GET` | `/auth/google/callback` | State CSRF | Menerima kode otorisasi Google OAuth, verifikasi email_verified, autolink akun manual atau buat user customer baru. |
| `GET` | `/daftar` | *None* | Halaman pemilih peran pendaftaran (Pelanggan, Mitra, Kurir). |
| `GET` | `/daftar/pelanggan` | *None* | Formulir registrasi pelanggan baru atau opsi daftar via Google OAuth. |
| `POST` | `/daftar/pelanggan` | RateLimiter, CSRF | Registrasi pelanggan multi-tabel (`users`, `customers`, `customer_addresses`) dalam transaksi PDO atomik. |
| `GET` | `/daftar/mitra` | *None* | Formulir registrasi mitra laundry. |
| `POST` | `/daftar/mitra` | RateLimiter, CSRF | Registrasi mitra laundry multi-tabel (`users`, `partners`, `partner_services`, `stores`, `services`) berstatus `menunggu_verifikasi`. |
| `GET` | `/daftar/kurir` | *None* | Formulir registrasi kurir armada. |
| `POST` | `/daftar/kurir` | RateLimiter, CSRF | Registrasi kurir multi-tabel (`users`, `couriers`, `drivers`) berstatus `menunggu_verifikasi`. |
| `GET` | `/verifikasi-menunggu` | `Auth` | Tampilan status verifikasi pendaftaran mitra/kurir. |
| `GET` | `/verifikasi-email/{token}` | *None* | Tautan konfirmasi verifikasi email dari pendaftaran manual. |
| `POST` | `/verifikasi-email/kirim-ulang` | `Auth`, RateLimiter, CSRF | Kirim ulang email verifikasi akun (dibatasi 3x / 5 menit). |
| `GET` | `/verifikasi-email-baru/{token}` | *None* | Tautan konfirmasi pergantian alamat email dari profil. |
| `GET` | `/lupa-password` | *None* | Halaman formulir permohonan reset kata sandi. |
| `POST` | `/lupa-password` | RateLimiter, CSRF | Menerbitkan token SHA-256 (60 menit) dan mengirimkan tautan reset password ke email. |
| `GET` | `/reset-password/{token}` | *None* | Formulir pemasangan password baru dari token. |
| `POST` | `/reset-password/{token}` | CSRF | Menyimpan password baru (min 8 karakter kombinasi huruf & angka) dan mengonsumsi token. |
| `GET` | `/admin/verifikasi` | `Auth`, `Role:admin` | Konsol verifikasi berkas dan persetujuan mitra laundry & kurir. |
| `POST` | `/admin/verifikasi/approve` | `Auth`, `Role:admin`, CSRF | Menyetujui akun mitra/kurir dan mengaktifkan toko/driver. |
| `POST` | `/admin/verifikasi/reject` | `Auth`, `Role:admin`, CSRF | Menolak akun mitra/kurir dengan alasan resmi. |

---

## 2. Profile & Onboarding

| Method | Endpoint | Guards | Request Body / Params | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/profile/complete` | `Auth` | *None* | Form onboarding customer untuk melengkapi No. HP, Alamat, RT, RW, dan Koordinat GPS. |
| `POST` | `/profile/complete` | `Auth`, CSRF | `_token`, `name`, `phone`, `full_address`, `rt`, `rw`, `district`, `city`, `latitude`, `longitude` | Menyimpan profil dan alamat utama pelanggan. |
| `GET` | `/profile` | `Auth` | *None* | Menampilkan profil pengguna dan alamat penjemputan. |
| `GET` | `/profile/edit` | `Auth` | *None* | Formulir pengeditan profil (nama, no HP normalisasi, email) dan manajemen password. |
| `POST` | `/profile/edit` | `Auth`, CSRF | `name`, `phone`, `email`, `current_password` | Memperbarui nama dan nomor HP; menerbitkan `pending_email` jika email diubah dengan verifikasi password. |
| `POST` | `/profile/change-password` | `Auth`, CSRF | `current_password`, `new_password`, `new_password_confirmation` | Memperbarui atau membuat password akun (mendukung akun Google yang ingin punya password manual). |

---

## 3. Dashboard Routes

| Method | Endpoint | Guards | Deskripsi |
| :--- | :--- | :--- | :--- |
| `GET` | `/dashboard` | `AuthMiddleware` | Dispatcher utama: me-redirect user ke dashboard spesifik perannya (`/customer/dashboard`, `/owner/dashboard`, `/driver/dashboard`). |
| `GET` | `/customer/dashboard` | `Auth`, `Role:customer`, `ProfileComplete` | Dashboard analitik dan pintasan cepat untuk pelanggan. |
| `GET` | `/owner/dashboard` | `Auth`, `Role:owner` | Dashboard pemilik toko laundry (analitik omzet, pesanan aktif, layanan terlaris). |
| `GET` | `/driver/dashboard` | `Auth`, `Role:driver` | Dashboard kurir (tugas aktif dan ketersediaan). |

---

## 4. Customer Order Management

| Method | Endpoint | Guards | Payload / Parameter | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/customer/orders` | `Auth`, `Role:customer`, `ProfileComplete` | *None* | Daftar seluruh riwayat pesanan milik customer yang sedang login. |
| `GET` | `/customer/orders/create` | `Auth`, `Role:customer`, `ProfileComplete` | *None* | Formulir pembuatan pesanan laundry baru (pilih layanan & alamat). |
| `POST` | `/customer/orders/confirm` | `Auth`, `Role:customer`, `ProfileComplete` | `_token`, `service_id`, `recipient_phone`, `full_address`, `rt`, `rw`, `latitude`, `longitude`, `notes` | Halaman konfirmasi rincian pesanan dan kalkulasi ongkir via Google Maps. |
| `POST` | `/customer/orders/store` | `Auth`, `Role:customer`, `ProfileComplete` | Sama seperti confirm | Menyimpan pesanan laundry ke database secara atomik (`pending_pickup`). |
| `GET` | `/customer/orders/{id}` | `Auth`, `Role:customer`, `ProfileComplete` | `id` (Order ID) | Menampilkan detail status pesanan, timeline tracking, kurir, dan tombol bayar. |
| `POST` | `/customer/orders/{id}/complete` | `Auth`, `Role:customer`, `ProfileComplete` | `_token` | Pelanggan mengonfirmasi pakaian bersih telah diterima (`completed`). |

---

## 5. Multi-Role AI Assistant

Asisten AI cerdas multi-peran yang terhubung langsung ke database dan peran ditentukan secara ketat di sisi server (`Session::get('user')['role']`). Menolak spoofing peran dari chat, menyapa dengan nama terdaftar (`users.name`), dan menjamin isolasi data mutlak antar pengguna.

| Method | Endpoint | Guards | Request Body | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `/assistant/message` | *Public / Session* | `{"message": "string"}` (JSON) | Endpoint chat widget AI serbaguna. Otomatis menentukan peran dari sesi aktif (`owner`, `customer`, `driver`, atau `tamu`). |
| `POST` | `/assistant/reset` | *Public / Session* | *None* | Menghapus riwayat percakapan sesi AI. |
| `GET` | `/customer/assistant` | `Auth`, `Role:customer`, `ProfileComplete` | `?order={id}` (Opsional) | Halaman penuh obrolan asisten AI khusus Customer (CS Laundry). |
| `POST` | `/customer/assistant/message` | `Auth`, `Role:customer`, `ProfileComplete` | `{"message": "string"}` (JSON) | Obrolan AI khusus customer terautentikasi. Mendukung auto-issuance QRIS Midtrans. |
| `POST` | `/customer/assistant/reset` | `Auth`, `Role:customer`, `ProfileComplete` | *None* | Reset riwayat obrolan customer. |
| `GET` | `/owner/assistant` | `Auth`, `Role:owner` | *None* | Halaman penuh obrolan asisten AI khusus Pemilik Laundry (Manajer Toko). |
| `POST` | `/owner/assistant/message` | `Auth`, `Role:owner` | `{"message": "string"}` (JSON) | Obrolan AI owner: analitik omzet, pesanan perlu ditimbang, verifikasi tunai, dan performa kurir toko. |
| `POST` | `/owner/assistant/reset` | `Auth`, `Role:owner` | *None* | Reset riwayat obrolan owner. |
| `GET` | `/driver/assistant` | `Auth`, `Role:driver` | `?order={id}` (Opsional) | Halaman penuh obrolan asisten AI khusus Kurir. |
| `POST` | `/driver/assistant/message` | `Auth`, `Role:driver` | `{"message": "string"}` (JSON) | Obrolan AI kurir: daftar tugas aktif, rincian alamat jemput/antar, nomor kontak darurat customer, dan link navigasi Google Maps. |
| `POST` | `/driver/assistant/reset` | `Auth`, `Role:driver` | *None* | Reset riwayat obrolan kurir. |

### Format Respon JSON & Attachment AI

```json
{
  "success": true,
  "reply": "Halo Budi, berikut kode QRIS untuk pesanan #ORD-20261019-ABCD ...",
  "attachment": {
    "type": "qris",
    "order_number": "ORD-20261019-ABCD",
    "amount": "Rp 45.000",
    "qris_url": "https://api.sandbox.midtrans.com/v2/qris/.../qr-code",
    "expired_at": "2026-10-19 19:30:00",
    "pay_page": "/payment/12/qris"
  }
}
```

```json
{
  "success": true,
  "reply": "Halo Budi Kurir, berikut informasi lokasi dan rute Google Maps untuk tugas penjemputan #ORD-20261019-ABCD ...",
  "attachment": {
    "type": "maps",
    "order_number": "ORD-20261019-ABCD",
    "address": "Jl. Rasuna Said Kav 5 RT 001 RW 002, Kec. Setiabudi, Jakarta Selatan",
    "view_url": "https://www.google.com/maps/search/?api=1&query=-6.215,106.83",
    "navigate_url": "https://www.google.com/maps/dir/?api=1&destination=-6.215,106.83&travelmode=driving",
    "customer_phone": "089876543210"
  }
}
```

---

## 6. Owner Store Management

| Method | Endpoint | Guards | Payload / Parameter | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/owner/orders` | `Auth`, `Role:owner` | *None* | Daftar seluruh pesanan yang masuk ke toko laundry milik owner. |
| `GET` | `/owner/drivers` | `Auth`, `Role:owner` | *None* | Daftar kurir terdaftar beserta ketersediaan dan nomor kontak. |
| `GET` | `/owner/orders/{id}` | `Auth`, `Role:owner` | `id` (Order ID) | Detail pesanan toko (rincian layanan, timeline, aksi timbang/konfirmasi bayar). |
| `POST` | `/owner/orders/{id}/weigh` | `Auth`, `Role:owner` | `_token`, `weight_kg` | Owner memasukkan berat pakaian hasil penimbangan. Memicu kalkulasi tagihan dan mengubah status ke `weighed`. |
| `GET` | `/owner/orders/{id}/assign` | `Auth`, `Role:owner` | `id` (Order ID) | Formulir pemilihan kurir untuk penjemputan atau pengantaran. |
| `POST` | `/owner/orders/{id}/assign` | `Auth`, `Role:owner` | `_token`, `driver_id` | Menugaskan kurir. Otomatis menetapkan `pickup_driver_id` atau `delivery_driver_id` sesuai fase pesanan. |
| `POST` | `/owner/orders/{id}/status` | `Auth`, `Role:owner` | `_token`, `new_status` (`ready_for_delivery` / `completed`) | Memperbarui status laundry (misal: selesai dicuci → siap diantar). |
| `POST` | `/owner/store/toggle-status` | `Auth`, `Role:owner` | `_token`, `is_open` (0 / 1, optional) | Mengubah status operasional toko laundry (Buka / Tutup Sementara) secara mandiri. |

---

## 7. Driver Trip Management

| Method | Endpoint | Guards | Payload / Parameter | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/driver/orders` | `Auth`, `Role:driver` | *None* | Daftar seluruh tugas penjemputan/pengantaran yang dialokasikan ke kurir yang login. |
| `GET` | `/driver/orders/{id}` | `Auth`, `Role:driver` | `id` (Order ID) | Detail tugas kurir (alamat jemput/antar, koordinat Google Maps, kontak pelanggan). Hanya bisa dibuka oleh kurir yang ditugaskan. |
| `POST` | `/driver/orders/{id}/status` | `Auth`, `Role:driver` | `_token`, `new_status`, `status_notes` | Memperbarui status perjalanan: `driver_on_the_way`, `arrived_at_customer`, `picked_up`, `arrived_at_store`, `delivered`, atau `completed`. |

---

## 8. Payment Gateway & Webhook

| Method | Endpoint | Guards | Payload / Parameter | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/payment/{orderId}` | `Auth`, `Role:customer`, `ProfileComplete` | `orderId` | Halaman pemilihan metode pembayaran (QRIS atau CASH). Hanya aktif jika order sudah berstatus `weighed`. |
| `POST` | `/payment/{orderId}/qris` | `Auth`, `Role:customer`, `ProfileComplete` | `_token` | Meminta pembuatan transaksi QRIS ke Midtrans API. Menghasilkan QR code dan masa berlaku 60 menit (`waiting_payment`). |
| `GET` | `/payment/{orderId}/qris` | `Auth`, `Role:customer`, `ProfileComplete` | `orderId` | Halaman tampilan kode QRIS untuk di-scan oleh customer beserta countdown timer. |
| `POST` | `/payment/{orderId}/cash` | `Auth`, `Role:customer`, `ProfileComplete` | `_token` | Pelanggan memilih metode pembayaran tunai saat pengantaran/toko (`waiting_payment`). |
| `GET` | `/payment/{orderId}/status` | `Auth`, `Role:customer`, `ProfileComplete` | `orderId` | Halaman tunggu konfirmasi pembayaran (auto-expire check). |
| `GET` | `/payment/{orderId}/success` | `Auth`, `Role:customer`, `ProfileComplete` | `orderId` | Halaman struk konfirmasi pembayaran berhasil. |
| `GET` | `/payment/{orderId}/expired` | `Auth`, `Role:customer`, `ProfileComplete` | `orderId` | Halaman pemberitahuan bahwa pembayaran telah melewati batas waktu dan dibatalkan. |
| `GET` | `/payment/{orderId}/poll` | `Auth`, `Role:customer`, `ProfileComplete` | `orderId` | Endpoint AJAX polling status pembayaran (mengembalikan JSON `{payment_status, order_status}`). |
| `POST` | `/payment/cash/{orderId}/confirm` | `Auth`, `Role:owner` | `_token` | Owner toko mengonfirmasi penerimaan uang tunai dari pelanggan (`paid` → `in_process`). |
| `POST` | `/payment/webhook/midtrans` | *Public Signature Guard* | Midtrans Notification JSON Payload | Endpoint Webhook publik. Memverifikasi signature SHA-512 Midtrans, mencocokkan nominal `gross_amount`, dan mengupdate status secara atomik. |
