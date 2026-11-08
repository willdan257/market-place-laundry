# Pasang AI Customer Assistant

## 1. Salin file
Ekstrak zip ini ke root `marketplace-laundry/` (struktur folder sudah sama).

**File baru** (aman, tidak menimpa apa pun):
- `app/Services/AIService.php`
- `app/Services/AI/*` (9 file)
- `app/Controllers/AIController.php`
- `app/Views/pages/assistant/chat.php`
- `app/Views/components/ai-widget.php` (widget melayang)
- `config/ai.php`
- `public/assets/css/assistant.css`, `public/assets/js/assistant.js`

**File lama yang diubah** (jika Anda punya perubahan lokal, JANGAN timpa; tempel snippet di bawah):
- `routes/web.php`, `app/Views/layouts/main.php`, `app/Views/pages/dashboard/customer.php`, `app/Views/pages/orders/show.php`, `.env.example`, `README.md`

Hapus file stub lama (sudah digantikan, tidak dipakai di mana pun):
`app/Services/AiAssistantService.php`

## 2. Isi `.env` (bukan hanya .env.example)
```ini
AI_PROVIDER=gemini        # atau anthropic
AI_API_KEY=API_KEY_ASLI
AI_MODEL=                 # kosong = default provider
AI_RATE_LIMIT_PER_MINUTE=8
AI_RATE_LIMIT_PER_DAY=100
AI_CA_BUNDLE=             # isi path cacert.pem jika error "SSL certificate problem"
```
Pastikan ekstensi `curl` aktif di `php.ini` (`extension=curl`).

## 3. Snippet manual (jika tidak menimpa file)

**app/Views/layouts/main.php** — tepat setelah `</main>` (ini yang memunculkan widget AI di semua halaman, tanpa login):
```php
<?php if (empty($hideAiWidget)): ?>
    <?php require __DIR__ . '/../components/ai-widget.php'; ?>
<?php endif; ?>
```

**routes/web.php** — tambahkan di atas, bersama `use` lainnya:
```php
use App\Controllers\AIController;
```
dan setelah blok rute order customer (setelah `$customerGuards` didefinisikan):
```php
// publik: mode ditentukan server dari session (customer login = data sendiri, selain itu = info publik)
$router->post('/assistant/message', [AIController::class, 'message']);
$router->post('/assistant/reset', [AIController::class, 'reset']);
// halaman chat penuh khusus customer
$router->get('/customer/assistant', [AIController::class, 'chatView'], $customerGuards);
$router->post('/customer/assistant/message', [AIController::class, 'message'], $customerGuards);
$router->post('/customer/assistant/reset', [AIController::class, 'reset'], $customerGuards);
```

**dashboard/customer.php** — tombol di header (sebelum link `/profile`):
```php
<a href="/customer/assistant" class="btn btn-outline-light rounded-pill px-3 fw-medium">
    <i class="bi bi-robot me-1"></i> Tanya AI
</a>
```

**orders/show.php** — di baris "Kembali ke Riwayat Order":
```php
<a href="/customer/assistant?order=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
    <i class="bi bi-robot me-1"></i> Tanya AI tentang pesanan ini
</a>
```

## 4. Dua mode
- **Tamu (belum login / owner / kurir):** hanya info layanan, harga, ongkir, FAQ. Tidak ada query data order sama sekali. Rate limit per IP (default 5/menit, 30/hari).
- **Customer login:** juga bisa tanya status order & pembayaran miliknya sendiri.

## 5. Uji
1. Pastikan MySQL menyala dan database sudah di-import.
2. Buka `http://localhost:8000/` TANPA login: tombol **Tanya AI** muncul di pojok kanan bawah.
3. Login sebagai customer, lalu coba 4 pertanyaan contoh (status order, total, pembayaran, harga). Error teknis dicatat di `storage/logs/ai.log`.

## 6. Chatbot Beranda (Node.js + Nous Research)
Ikon chat di halaman **Beranda** memakai server Node.js di folder `chatbot-server/`.

1. Salin file baru: `chatbot-server/*`, `public/assets/js/chatbot.js`, `public/assets/css/chatbot.css`.
2. Ubah 2 file lama (atau tempel snippet):
   - `app/Views/pages/home.php`: tambahkan di akhir file
     ```php
     <div id="nousChatbot" data-api="<?= htmlspecialchars((string) \App\Helpers\Env::get('CHATBOT_API_URL', 'http://localhost:3001/api/chat')) ?>"></div>
     <link rel="stylesheet" href="/assets/css/chatbot.css">
     <script src="/assets/js/chatbot.js" defer></script>
     ```
   - `app/Controllers/HomeController.php`: tambahkan `'hideAiWidget' => true` pada array data `render('home', [...])` (agar tidak ada dua ikon chat di Beranda).
3. Jalankan server (terminal baru):
   ```
   cd chatbot-server
   copy .env.example .env
   ```
   Buka `chatbot-server/.env`, isi `NOUS_API_KEY=...`, lalu `npm start`.
4. Buka `http://localhost:8000/` dan klik ikon chat di kanan bawah.

**Keamanan:** jangan menaruh API key di file PHP/JS/HTML website atau membagikannya di chat. Karena key sudah pernah ditulis di percakapan, sebaiknya buat key baru di Nous Portal dan cabut yang lama.
