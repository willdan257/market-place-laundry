# Chatbot Beranda (Node.js + Nous Research)

Server Node.js kecil tanpa dependensi (butuh Node 18+). API key Nous hanya disimpan di sini, tidak pernah dikirim ke browser.

## Menjalankan
```bash
cd chatbot-server
copy .env.example .env      # Windows (Mac/Linux: cp)
# buka .env, isi NOUS_API_KEY
npm start                   # atau: node server.js
```
Cek: buka http://localhost:3001/health, harus muncul `{"ok":true,"configured":true}`.

Server ini harus tetap berjalan selama website dipakai (jalankan di terminal terpisah dari `php -S`).

## Tes otomatis (tanpa API key, memakai server Nous palsu)
```bash
npm test
```

## Catatan
- Bot ini PUBLIK: hanya tahu info umum dari `knowledge.json` (layanan, harga, ongkir, FAQ). Tidak ada akses database atau data pesanan.
- Ubah harga/FAQ di `knowledge.json`, lalu restart server.
- Model default `Hermes-4-405B`. Ganti lewat `NOUS_MODEL` jika Nous Portal memakai nama lain.
- Saat deploy: set `ALLOWED_ORIGINS` ke domain website Anda, dan `CHATBOT_API_URL` di `.env` website PHP ke alamat server ini.
