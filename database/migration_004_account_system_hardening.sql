-- ======================================================
-- MIGRATION 004: Production Account System Hardening
-- Database: marketplace_laundry
-- ======================================================

USE `marketplace_laundry`;

-- 1. Add picture and pending_email columns to users table if not exists
SET @col_picture = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'picture');
SET @sql_picture = IF(@col_picture = 0, 
    "ALTER TABLE `users` ADD COLUMN `picture` VARCHAR(255) NULL AFTER `email_verified_at`", 
    "SELECT 'picture already exists'");
PREPARE stmt_pic FROM @sql_picture; EXECUTE stmt_pic; DEALLOCATE PREPARE stmt_pic;

SET @col_pending = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'pending_email');
SET @sql_pending = IF(@col_pending = 0, 
    "ALTER TABLE `users` ADD COLUMN `pending_email` VARCHAR(150) NULL AFTER `picture`", 
    "SELECT 'pending_email already exists'");
PREPARE stmt_pen FROM @sql_pending; EXECUTE stmt_pen; DEALLOCATE PREPARE stmt_pen;

-- 2. Create email_verifications table
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `type` ENUM('register', 'email_change') NOT NULL DEFAULT 'register',
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `used_at` TIMESTAMP NULL,
    INDEX `idx_ev_token` (`token_hash`),
    INDEX `idx_ev_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create password_resets table
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `used_at` TIMESTAMP NULL,
    INDEX `idx_pr_token` (`token_hash`),
    INDEX `idx_pr_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
