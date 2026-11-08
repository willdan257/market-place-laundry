-- ============================================================
-- Migrasi Penambahan Kolom Google ID dan Tabel Penunjang Auth
-- Jalankan manual di phpMyAdmin jika kolom belum ada di database
-- ============================================================

-- Tambahkan kolom google_id jika belum ada pada tabel users
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `google_id` VARCHAR(255) NULL UNIQUE AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `picture` VARCHAR(255) NULL AFTER `email_verified_at`;

-- Pastikan indeks google_id terpasang
CREATE INDEX IF NOT EXISTS `idx_users_google_id` ON `users` (`google_id`);

