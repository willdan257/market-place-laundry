<?php

use App\Helpers\Env;
use App\Models\Setting;

return (function () {
    // Utamakan membaca dari database settings, fallback ke .env
    $dbAiEnabled = Setting::get('ai_enabled');
    $dbAiProvider = Setting::get('ai_provider');
    $dbAiModel = Setting::get('ai_model');
    $dbAiApiKey = Setting::get('ai_api_key', true);

    $provider = !empty($dbAiProvider) ? strtolower($dbAiProvider) : strtolower((string) Env::get('AI_PROVIDER', 'gemini'));
    $apiKey = !empty($dbAiApiKey) ? trim($dbAiApiKey) : trim((string) Env::get('AI_API_KEY', ''));
    $model = !empty($dbAiModel) ? trim($dbAiModel) : trim((string) Env::get('AI_MODEL', ''));

    /**
     * Konfigurasi AI Customer Assistant.
     *
     * AI_PROVIDER : "gemini" (default), "nous", atau "anthropic"
     * AI_API_KEY  : API key dari provider yang dipilih
     * AI_MODEL    : opsional, kosongkan untuk memakai model default provider
     */
    return [
        'enabled'           => ($dbAiEnabled !== null) ? ($dbAiEnabled === '1') : true,
        'provider'          => $provider,
        'api_key'           => $apiKey,
        'model'             => $model,

        // Jika cURL di XAMPP error "SSL certificate problem", isi dengan path cacert.pem
        'ca_bundle'         => trim((string) Env::get('AI_CA_BUNDLE', '')),

        'timeout'           => 25,   // detik, total request ke provider
        'connect_timeout'   => 8,    // detik
        'max_output_tokens' => 250,
        'temperature'       => 0.2,

        'max_message_length' => 500, // karakter per pertanyaan
        'history_messages'   => 6,   // jumlah pesan terakhir yang dikirim sebagai konteks percakapan
        'recent_orders'      => 5,   // jumlah order terbaru customer yang dimuat sebagai konteks

        'rate_limit' => [
            'per_minute' => max(1, (int) Env::get('AI_RATE_LIMIT_PER_MINUTE', 8)),
            'per_day'    => max(1, (int) Env::get('AI_RATE_LIMIT_PER_DAY', 100)),
        ],

        // Batas untuk pengunjung yang belum login (per alamat IP) - lebih ketat
        'guest_rate_limit' => [
            'per_minute' => max(1, (int) Env::get('AI_GUEST_RATE_LIMIT_PER_MINUTE', 5)),
            'per_day'    => max(1, (int) Env::get('AI_GUEST_RATE_LIMIT_PER_DAY', 30)),
        ],

        // Kontak bantuan (sama dengan footer aplikasi)
        'support' => [
            'email' => 'support@marketplacelaundry.id',
            'phone' => '+62 812-3456-7890',
        ],

        // FAQ statis. Ongkir & harga layanan TIDAK ditulis di sini karena diambil dari config/DB.
        'faq' => [
            [
                'q' => 'Bagaimana cara membuat pesanan laundry?',
                'a' => 'Login sebagai customer dan lengkapi profil (HP, alamat, RT/RW, koordinat). Lalu buka menu Buat Order Laundry, pilih layanan, periksa alamat penjemputan, dan konfirmasi pesanan.',
            ],
            [
                'q' => 'Bagaimana alur pesanan laundry?',
                'a' => 'Pesanan dibuat, pemilik laundry menugaskan kurir, kurir menjemput pakaian ke alamat Anda, pakaian tiba di toko dan ditimbang, Anda membayar, pakaian dicuci, lalu diantar kembali.',
            ],
            [
                'q' => 'Kapan saya bisa membayar?',
                'a' => 'Pembayaran baru tersedia setelah pakaian ditimbang di toko (status "Sudah Ditimbang"), karena total final bergantung pada berat/jumlah sebenarnya.',
            ],
            [
                'q' => 'Metode pembayaran apa yang tersedia?',
                'a' => 'QRIS dan Tunai (Cash). QRIS berlaku sekitar 1 jam sejak dibuat. Pembayaran tunai berlaku sekitar 7 hari dan harus dikonfirmasi oleh pemilik laundry sebelum pesanan diproses.',
            ],
            [
                'q' => 'Bagaimana total pembayaran dihitung?',
                'a' => 'Total = harga layanan x berat/jumlah + ongkos kirim - diskon (jika ada). Sebelum ditimbang, total hanya perkiraan awal.',
            ],
            [
                'q' => 'Berapa lama laundry selesai?',
                'a' => 'Tergantung layanan yang dipilih (lihat estimasi durasi di daftar layanan). Hitungan dimulai saat laundry mulai diproses, yaitu setelah pembayaran terkonfirmasi.',
            ],
            [
                'q' => 'Bagaimana jika ingin membatalkan pesanan, refund, atau komplain?',
                'a' => 'Asisten tidak dapat membatalkan pesanan atau memproses refund. Silakan hubungi bantuan pelanggan.',
            ],
        ],
    ];
})();
