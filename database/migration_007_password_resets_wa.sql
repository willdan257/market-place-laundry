-- ============================================================
-- Migrasi Tabel Password Resets untuk Kode OTP WhatsApp
-- Jalankan di phpMyAdmin jika tabel belum ada atau ingin disesuaikan
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `phone` VARCHAR(30) NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `used_at` TIMESTAMP NULL,
    INDEX `idx_pr_token` (`token_hash`),
    INDEX `idx_pr_user` (`user_id`),
    INDEX `idx_pr_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambahkan kolom phone dan attempts jika tabel password_resets sudah ada sebelumnya
ALTER TABLE `password_resets`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(30) NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `token_hash`;

