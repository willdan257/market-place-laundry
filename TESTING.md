# 🧪 TESTING DOCUMENTATION - MARKETPLACE LAUNDRY

Dokumen ini mendokumentasikan seluruh arsitektur pengujian, skenario integrasi end-to-end, penanganan konkurensi, keamanan otorisasi, dan hasil pengujian aplikasi **Marketplace Laundry**.

---

## 📋 Ringkasan Hasil Pengujian

Pengujian integrasi otomatis dijalankan menggunakan test runner native PHP:
```bash
php tests/run_tests.php
```
atau
```bash
composer test
```

### Hasil Eksekusi Test Runner:
```
============================================================
🚀 RUNNING MARKETPLACE LAUNDRY FINAL INTEGRATION TESTS
============================================================
[SETUP] Initializing Test Fixtures in Database...
  ✔ [PASS] Created test Owner user
  ✔ [PASS] Created test Store with coordinates
  ✔ [PASS] Created test Service (Rp 10.000 / kg)
  ✔ [PASS] Created two test Drivers (Driver 1 & Driver 2)
  ✔ [PASS] Created two test Customers (Customer 1 & Customer 2)
  ✔ [PASS] Customer 1 profile & address completed

[TEST 1] Testing End-to-End QRIS Flow...
  ✔ [PASS] Order created with pending_pickup status
  ✔ [PASS] Owner assigned pickup driver -> driver_assigned
  ✔ [PASS] Driver on the way
  ✔ [PASS] Driver arrived at customer
  ✔ [PASS] Driver picked up clothes
  ✔ [PASS] Driver arrived at store
  ✔ [PASS] Owner weighed laundry -> status weighed
  ✔ [PASS] Invoice total calculated accurately (Subtotal + Ongkir)
  ✔ [PASS] QRIS Payment record created -> waiting_payment
  ✔ [PASS] Webhook successfully processed -> payment paid
  ✔ [PASS] Order transitioned to in_process upon payment confirmation
  ✔ [PASS] Owner marked laundry as ready_for_delivery
  ✔ [PASS] Delivery driver assigned -> delivery_assigned with delivery_driver_id
  ✔ [PASS] Driver marked order as delivered
  ✔ [PASS] Order fully marked as completed

[TEST 2] Testing End-to-End CASH Flow...
  ✔ [PASS] Customer created CASH payment record
  ✔ [PASS] Order status updated to waiting_payment for CASH
  ✔ [PASS] Owner confirmed CASH payment -> paid
  ✔ [PASS] Order status updated to in_process after CASH confirmation
  ✔ [PASS] CASH order completed successfully

[TEST 3] Testing Payment Expiration & Automatic Cancellation...
  ✔ [PASS] Order 3 waiting_payment created with 1-hour expiry
  ✔ [PASS] expireOverduePayments processed overdue record
  ✔ [PASS] Payment status became EXPIRED
  ✔ [PASS] Order followed cancellation rule -> order_status CANCELLED
  ✔ [PASS] Notification sent to Customer regarding payment expiration & cancellation

[TEST 4] Testing Strict Authorization & Access Control...
  ✔ [PASS] Customer 2 CANNOT access Customer 1's order (getOrderDetail returns null)
  ✔ [PASS] Customer 1 CAN access own order
  ✔ [PASS] Driver 2 (assigned as delivery driver) CAN access Order 1
  ✔ [PASS] Driver 2 CANNOT modify Order 2 assigned strictly to Driver 1
  ✔ [PASS] Customer blocked by RoleMiddleware from Owner Dashboard
  ✔ [PASS] Driver blocked by RoleMiddleware from Owner Dashboard
  ✔ [PASS] Owner permitted by RoleMiddleware into Owner Dashboard

[TEST 5] Testing Webhook Idempotency & Tamper Protection...
  ✔ [PASS] Duplicate webhook gracefully skipped without re-processing
  ✔ [PASS] No duplicate order status history inserted for in_process
  ✔ [PASS] Underpayment / gross_amount mismatch caught and rejected
  ✔ [PASS] Tampered payment marked as failed in database

[TEST 6] Testing Payment Method Switching (Cash -> QRIS -> Cash)...
  ✔ [PASS] Order 5 initially selected CASH
  ✔ [PASS] Switched from CASH to QRIS without duplicate key error
  ✔ [PASS] Switched back to CASH cleanly

============================================================
TEST RESULTS: 45 Passed, 0 Failed (100% Success)
============================================================
```

---

## 🔄 Rincian Skenario Pengujian End-to-End

### 1. CUSTOMER QRIS Flow
Alur lengkap siklus pesanan non-tunai (QRIS Midtrans):
1. **Login Google**: Autentikasi OAuth Google callback atau dev-simulate login (`/auth/google/dev-simulate?role=customer`).
2. **Profile & Alamat**: Pelanggan melengkapi nama, nomor telepon, alamat lengkap, RT, RW, koordinat latitude & longitude (`/profile/complete`). `ProfileCompleteMiddleware` memastikan pesanan tidak dapat dibuat tanpa data ini.
3. **Create Order**: Pelanggan memilih jenis layanan laundry dan menentukan lokasi penjemputan. Server menghitung jarak akurat via Google Maps Distance Matrix API (dengan fallback Haversine jika offline) dan tarif ongkir dinamis (`/customer/orders/store`). Status order: `pending_pickup`.
4. **Kurir Ditugaskan (Pickup)**: Owner toko laundry membuka `/owner/orders/{id}/assign` dan menugaskan kurir penjemputan. Status order: `driver_assigned`. Notifikasi terkirim ke customer.
5. **Perjalanan Penjemputan (Driver)**: Kurir memperbarui status secara bertahap:
   - `driver_on_the_way` (Kurir menuju lokasi pelanggan)
   - `arrived_at_customer` (Kurir tiba di rumah pelanggan)
   - `picked_up` (Pakaian kotor diserahterimakan ke kurir)
   - `arrived_at_store` (Pakaian tiba di toko laundry)
6. **Penimbangan (Owner)**: Owner toko menimbang berat pakaian pada `/owner/orders/{id}/weigh`. Sistem menghitung ulang subtotal = `berat_kg * tarif_per_kg`, total tagihan = `subtotal + ongkir`. Status order: `weighed`.
7. **Invoice & QRIS Generation**: Customer menerima rincian invoice pada `/payment/{orderId}` dan memilih metode pembayaran QRIS (`/payment/{orderId}/qris`). Sistem menghasilkan referensi transaksi unik Midtrans, link QR code dinamis, dan menetapkan `expired_at` (60 menit). Status order: `waiting_payment`.
8. **Payment Settlement (Webhook)**: Midtrans mengirim HTTP POST Webhook ke `/payment/webhook/midtrans` dengan status `settlement`. Sistem memverifikasi SHA-512 signature, mencocokkan `gross_amount`, mengunci baris pembayaran secara atomik (`SELECT FOR UPDATE`), mengubah status payment menjadi `paid`, dan memajukan order ke `in_process`.
9. **Laundry Diproses**: Pakaian dicuci, dikeringkan, dan disetrika oleh toko. Setelah selesai, Owner menandai pesanan sebagai `ready_for_delivery` (Siap Diantar).
10. **Delivery**: Owner menugaskan kurir pengantaran melalui form assign (`/owner/orders/{id}/assign`), status menjadi `delivery_assigned`. Kurir mengantarkan pakaian bersih ke rumah pelanggan dan menandai status `delivered`.
11. **Completed**: Pelanggan mengonfirmasi penerimaan pakaian bersih pada `/customer/orders/{id}/complete` (atau owner mengonfirmasi selesai). Status akhir: `completed`.

---

### 2. CUSTOMER CASH Flow
Alur pembayaran tunai di tempat:
1. Alur penjemputan dan penimbangan berjalan identik hingga status `weighed`.
2. Pelanggan memilih metode **Bayar Tunai (CASH)** via `POST /payment/{orderId}/cash`.
3. Status payment dibuat sebagai `pending` (metode `cash`), dan status order menjadi `waiting_payment`.
4. Kurir/Owner menerima uang tunai dari pelanggan.
5. Owner toko membuka detail order dan menekan **"Konfirmasi Terima Uang Tunai"** (`POST /payment/cash/{orderId}/confirm`).
6. Transaksi pembayaran ditandai sebagai `paid`, dan status pesanan otomatis berpindah ke `in_process`.
7. Proses dilanjutkan ke pengantaran (`ready_for_delivery` → `delivery_assigned` → `delivered` → `completed`).

---

### 3. Payment Expiration & Cancellation Rule
Pengujian batas waktu pembayaran:
1. Transaksi pembayaran dibuat dengan masa berlaku 1 jam (`expired_at = NOW() + 1 HOUR`).
2. Jika pembayaran tidak diselesaikan hingga melewati batas waktu:
   - Status payment berubah menjadi **`expired`**.
   - Sistem menjalankan cancellation rule: status order otomatis diubah menjadi **`cancelled`** dengan catatan audit: *"Dibatalkan otomatis karena batas waktu pembayaran terlewati."*.
   - Notifikasi bertipe `payment_expired` dikirim ke dashboard akun pelanggan.
3. Mekanisme ini diuji melalui:
   - Polling AJAX (`/payment/{orderId}/poll`).
   - Akses halaman tunggu (`/payment/{orderId}/status` & `/payment/{orderId}/qris`).
   - Scheduler/Cron otomatis (`PaymentService::expireOverduePayments()`).
   - Webhook notifikasi expired dari Midtrans (`transaction_status = 'expire'`).

---

### 4. Otorisasi & Access Control (RBAC)
Pengujian keamanan hak akses antar-peran dan multi-tenancy:
1. **Isolasi Pesanan Antar Customer**:
   - Customer A tidak dapat melihat detail pesanan milik Customer B (`/customer/orders/{id}`).
   - Customer A tidak dapat membuat pembayaran QRIS/CASH atas pesanan milik Customer B.
2. **Isolasi Tugas Kurir**:
   - Kurir A tidak dapat melihat atau mengubah status pesanan yang ditugaskan kepada Kurir B (`/driver/orders/{id}`).
3. **Proteksi Dashboard Owner**:
   - Customer yang mengakses `/owner/*` otomatis dicegat oleh `RoleMiddleware` dan dialihkan ke `/dashboard` dengan pesan error akses ditolak.
   - Kurir yang mengakses `/owner/*` dicegat oleh `RoleMiddleware`.
   - Hanya pengguna dengan kolom `role = 'owner'` yang memiliki akses ke modul toko.

---

### 5. Konkurensi, Transaksi Database, & Idempotensi
Pengujian keandalan backend terhadap kondisi perlombaan (*race conditions*):
1. **Pencegahan Duplicate Webhook**:
   - Webhook Midtrans pertama memproses settlement dan mencatat histori status.
   - Panggilan webhook kedua dengan payload yang sama ditolak secara idempoten (`status: skipped`) tanpa menduplikasi notifikasi maupun riwayat status order.
2. **Row-Level Locking (`SELECT FOR UPDATE`)**:
   - Operasi perubahan status pembayaran dan konfirmasi CASH dibungkus dalam blok `beginTransaction()` dengan lock baris `FOR UPDATE` sehingga thread konkurensi tidak dapat melakukan double-credit.
3. **Validasi Jumlah Pembayaran (Anti-Tampering)**:
   - Jika payload webhook mengirimkan `gross_amount` yang tidak sesuai dengan `amount` di tabel `payments`, pembayaran langsung digagalkan (`payment_status = 'failed'`) dan dilemparkan exception keamanan.
4. **Pencegahan Error Duplikasi Key `order_id`**:
   - Kolom `payments.order_id` bersifat `UNIQUE`. Ketika pelanggan berganti metode bayar dari CASH ke QRIS (atau sebaliknya), sistem memperbarui record yang ada (`updateToQris` / `updateToCash`) alih-alih melakukan `INSERT` baru yang akan menyebabkan fatal crash.

---

## 🚀 Cara Menjalankan Pengujian

Jalankan perintah berikut di terminal:
```bash
# Melalui CLI PHP
php tests/run_tests.php

# Atau melalui Composer
composer test

# Atau melalui npm
npm test
```
Semua fixture pengujian dibuat otomatis di dalam database `marketplace_laundry`.
