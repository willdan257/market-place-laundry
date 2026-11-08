-- =========================================================================
-- PERBAIKAN STRUKTUR TABEL password_resets (UNTUK KODE OTP WHATSAPP)
-- Jalankan query ini di phpMyAdmin pada database `marketplace_laundry`.
-- =========================================================================

-- 1. Tambahkan kolom phone dan attempts jika belum ada (aman, tidak menghapus data)
ALTER TABLE `password_resets`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(30) NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `token_hash`;

-- 2. Tambahkan index untuk pencarian cepat berdasarkan phone
CREATE INDEX IF NOT EXISTS `idx_pr_phone` ON `password_resets` (`phone`);

-- 3. Pastikan kolom google_id dan picture pada tabel users juga ada (untuk fitur Login Google)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `google_id` VARCHAR(255) NULL UNIQUE AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `picture` VARCHAR(255) NULL AFTER `email_verified_at`;

