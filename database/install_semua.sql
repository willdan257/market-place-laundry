-- ======================================================================
-- MARKETPLACE LAUNDRY - DATABASE LENGKAP (install_semua.sql)
-- Urutan gabungan:
--   1. marketplace_laundry_complete.sql
--   2. DROP + CREATE password_resets (versi OTP WA dengan kolom phone & attempts)
--   3. migration_005_chat_system.sql (order_chats)
--   4. create_settings_table.sql (settings)
--   5. create_wa_logs_table.sql (wa_logs)
--   6. seed_google_auth_settings.sql (settings OAuth)
--   7. migrations/admin_login_log.sql (admin_login_log)
-- Aman untuk MySQL 8 dan MariaDB 10.4+ (tanpa ADD COLUMN IF NOT EXISTS)
-- ======================================================================

-- ======================================================================
-- MARKETPLACE LAUNDRY - DATABASE LENGKAP (1 FILE)
-- Gabungan: schema.sql + migration 002 + migration 003 + migration 004
--           + UNIQUE index nomor HP (sebelumnya hanya ada di run_migration_004.php)
--
-- Engine   : MySQL 8.0 / MariaDB 10.4+ (XAMPP / Laragon)
-- Charset  : utf8mb4 / utf8mb4_unicode_ci
--
-- CARA PAKAI : phpMyAdmin -> tab Import -> pilih file ini -> Go
--
-- PERHATIAN  : file ini MENGHAPUS & MEMBUAT ULANG semua tabel.
--              Pakai hanya untuk database baru / kosong.
--              Jangan di-import ke database yang sudah berisi data penting.
-- ======================================================================

CREATE DATABASE IF NOT EXISTS `marketplace_laundry`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `marketplace_laundry`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `email_verifications`;
DROP TABLE IF EXISTS `partner_services`;
DROP TABLE IF EXISTS `couriers`;
DROP TABLE IF EXISTS `partners`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `order_status_histories`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `laundry_orders`;
DROP TABLE IF EXISTS `drivers`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `stores`;
DROP TABLE IF EXISTS `customer_addresses`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 1. USERS
-- (sudah termasuk kolom dari migrasi 003 & 004)
-- ==========================================
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `google_id` VARCHAR(255) NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NULL,
    `phone` VARCHAR(20) NULL,
    `role` ENUM('customer', 'owner', 'driver', 'admin', 'pelanggan', 'mitra', 'kurir') NOT NULL DEFAULT 'pelanggan',
    `status` ENUM('aktif', 'menunggu_verifikasi', 'ditolak', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `email_verified_at` TIMESTAMP NULL,
    `picture` VARCHAR(255) NULL,
    `pending_email` VARCHAR(150) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_users_phone` (`phone`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 2. CUSTOMER ADDRESSES
-- ==========================================
CREATE TABLE `customer_addresses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `label` VARCHAR(50) NOT NULL DEFAULT 'Rumah',
    `recipient_name` VARCHAR(150) NOT NULL,
    `recipient_phone` VARCHAR(20) NOT NULL,
    `full_address` TEXT NOT NULL,
    `rt` VARCHAR(10) NULL,
    `rw` VARCHAR(10) NULL,
    `district` VARCHAR(100) NULL,
    `city` VARCHAR(100) NULL,
    `postal_code` VARCHAR(10) NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_address_user` (`user_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 3. STORES
-- ==========================================
CREATE TABLE `stores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `owner_id` BIGINT UNSIGNED NOT NULL,
    `store_name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `full_address` TEXT NOT NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `is_open` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_stores_owner` (`owner_id`),
    INDEX `idx_stores_status` (`is_open`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 4. SERVICES
-- ==========================================
CREATE TABLE `services` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` BIGINT UNSIGNED NOT NULL,
    `service_name` VARCHAR(100) NOT NULL,
    `unit` ENUM('kg', 'pcs', 'meter') NOT NULL DEFAULT 'kg',
    `price_per_unit` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `estimated_duration_hours` INT UNSIGNED NOT NULL DEFAULT 24,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE,
    INDEX `idx_services_store` (`store_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 5. DRIVERS
-- ==========================================
CREATE TABLE `drivers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `vehicle_type` VARCHAR(50) NOT NULL DEFAULT 'Motorcycle',
    `license_plate` VARCHAR(20) NOT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `current_latitude` DECIMAL(10, 8) NULL,
    `current_longitude` DECIMAL(11, 8) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_driver_user` (`user_id`),
    INDEX `idx_driver_availability` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 6. LAUNDRY ORDERS
-- (sudah termasuk status 'waiting_payment' dari migrasi 002)
-- ==========================================
CREATE TABLE `laundry_orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `store_id` BIGINT UNSIGNED NOT NULL,
    `address_id` BIGINT UNSIGNED NOT NULL,
    `pickup_driver_id` BIGINT UNSIGNED NULL,
    `delivery_driver_id` BIGINT UNSIGNED NULL,
    `weight_kg` DECIMAL(8, 2) NULL DEFAULT 0.00,
    `subtotal_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `additional_fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `distance_km` DECIMAL(8, 2) NULL DEFAULT 0.00,
    `order_status` ENUM(
        'pending_pickup',
        'driver_assigned',
        'driver_on_the_way',
        'arrived_at_customer',
        'picked_up',
        'arrived_at_store',
        'weighed',
        'waiting_payment',
        'in_process',
        'ready_for_delivery',
        'delivery_assigned',
        'delivered',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_pickup',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`address_id`) REFERENCES `customer_addresses`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`pickup_driver_id`) REFERENCES `drivers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`delivery_driver_id`) REFERENCES `drivers`(`id`) ON DELETE SET NULL,
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_order_customer` (`customer_id`),
    INDEX `idx_order_store` (`store_id`),
    INDEX `idx_order_status` (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 7. ORDER ITEMS
-- ==========================================
CREATE TABLE `order_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    `qty` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `price_per_unit` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `laundry_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE RESTRICT,
    INDEX `idx_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 8. PAYMENTS
-- (sudah termasuk kolom gateway/QRIS dari migrasi 002 & status 'cancelled')
-- ==========================================
CREATE TABLE `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `payment_method` ENUM('qris', 'cash', 'bank_transfer') NOT NULL DEFAULT 'qris',
    `payment_status` ENUM('pending', 'paid', 'expired', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    `amount` DECIMAL(12, 2) NOT NULL,
    `expired_at` DATETIME NOT NULL,
    `paid_at` DATETIME NULL,
    `transaction_reference` VARCHAR(100) NULL,
    `gateway_transaction_id` VARCHAR(100) NULL,
    `gateway_response` JSON NULL,
    `idempotency_key` VARCHAR(100) NULL,
    `payment_type` VARCHAR(50) NULL COMMENT 'e.g. qris, bank_transfer',
    `qris_url` TEXT NULL COMMENT 'QRIS image URL from Midtrans',
    `qris_string` TEXT NULL COMMENT 'Raw QRIS string for QR generation',
    `va_number` VARCHAR(50) NULL,
    `confirmed_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `laundry_orders`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `idx_payment_idempotency` (`idempotency_key`),
    INDEX `idx_payment_gateway_tx` (`gateway_transaction_id`),
    INDEX `idx_payment_order` (`order_id`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_payment_expiration` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 9. ORDER STATUS HISTORIES
-- ==========================================
CREATE TABLE `order_status_histories` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `previous_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `laundry_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_history_order` (`order_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 10. NOTIFICATIONS
-- ==========================================
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'order_update',
    `reference_id` BIGINT UNSIGNED NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 11. CUSTOMERS (migrasi 003)
-- ==========================================
CREATE TABLE `customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `alamat_utama` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_customers_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 12. PARTNERS (migrasi 003)
-- ==========================================
CREATE TABLE `partners` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `nama_usaha` VARCHAR(150) NOT NULL,
    `alamat` TEXT NOT NULL,
    `lat` DECIMAL(10, 8) NULL,
    `lng` DECIMAL(11, 8) NULL,
    `jam_operasional` VARCHAR(100) NULL,
    `dokumen` VARCHAR(255) NULL,
    `status_verifikasi` ENUM('menunggu_verifikasi', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu_verifikasi',
    `alasan_penolakan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_partners_user` (`user_id`),
    INDEX `idx_partners_status` (`status_verifikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 13. COURIERS (migrasi 003)
-- ==========================================
CREATE TABLE `couriers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `jenis_kendaraan` VARCHAR(50) NOT NULL DEFAULT 'Motor',
    `plat_nomor` VARCHAR(20) NOT NULL,
    `dokumen` VARCHAR(255) NULL,
    `status_verifikasi` ENUM('menunggu_verifikasi', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu_verifikasi',
    `alasan_penolakan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_couriers_user` (`user_id`),
    INDEX `idx_couriers_status` (`status_verifikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 14. PARTNER SERVICES (migrasi 003)
-- ==========================================
CREATE TABLE `partner_services` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` BIGINT UNSIGNED NOT NULL,
    `nama_layanan` VARCHAR(100) NOT NULL,
    `tipe` ENUM('kiloan', 'satuan', 'express') NOT NULL DEFAULT 'kiloan',
    `harga` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    INDEX `idx_ps_partner` (`partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 15. EMAIL VERIFICATIONS (migrasi 004)
-- ==========================================
CREATE TABLE `email_verifications` (
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

-- ==========================================
-- 16. PASSWORD RESETS (migrasi 004)
-- ==========================================
CREATE TABLE `password_resets` (
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

-- ==========================================
-- 16. PASSWORD RESETS (VERSI OTP WHATSAPP)
-- Sesuai migration_007_password_resets_wa.sql
-- ==========================================
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
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
    INDEX `idx_pr_phone` (`phone`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Migration 005: Order Chat History System
-- Menambahkan tabel riwayat chat antar customer, mitra, kurir, dan admin
-- ==========================================================

CREATE TABLE IF NOT EXISTS `order_chats` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT(20) UNSIGNED NOT NULL,
    `sender_id` BIGINT(20) UNSIGNED NOT NULL,
    `sender_role` ENUM('customer', 'owner', 'driver', 'admin') NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_chats_order` (`order_id`),
    INDEX `idx_order_chats_sender` (`sender_id`),
    INDEX `idx_order_chats_role` (`sender_role`),
    INDEX `idx_order_chats_created_at` (`created_at`),
    CONSTRAINT `fk_order_chats_order` FOREIGN KEY (`order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_chats_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Migrasi: Tabel Pengaturan Sistem (settings)
-- Jalankan di phpMyAdmin pada database marketplace_laundry
-- ==========================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default values (will not overwrite if key already exists)
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('app_name', 'Marketplace Laundry'),
('business_name', 'PT Marketplace Laundry Indonesia'),
('contact_email', 'admin@marketplacelaundry.com'),
('timezone', 'Asia/Jakarta'),
('logo_path', NULL),
('ai_enabled', '0'),
('ai_provider', 'gemini'),
('ai_model', 'gemini-1.5-flash'),
('ai_api_key', NULL),
('wa_enabled', '0'),
('wa_token', NULL),
('wa_admin_phone', ''),
('wa_notify_order_status', '0');

-- ==========================================================
-- Migrasi: Tabel Log Notifikasi WhatsApp (wa_logs)
-- Jalankan di phpMyAdmin pada database marketplace_laundry
-- ==========================================================

CREATE TABLE IF NOT EXISTS `wa_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `target_phone` VARCHAR(30) NOT NULL,
  `recipient_type` ENUM('customer', 'store_admin', 'system_admin') NOT NULL DEFAULT 'customer',
  `notification_type` VARCHAR(50) NOT NULL, -- e.g. 'new_order', 'status_update', 'driver_update_summary'
  `status` ENUM('sent', 'failed', 'skipped') NOT NULL DEFAULT 'sent',
  `error_message` VARCHAR(255) DEFAULT NULL,
  `message_preview` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wa_order_status` (`order_id`, `notification_type`, `recipient_type`),
  KEY `idx_wa_target` (`target_phone`),
  KEY `idx_wa_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Migrasi: Pengaturan Kredensial Google OAuth 2.0
-- Jalankan di phpMyAdmin jika tabel settings sudah dibuat
-- ==========================================================

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('google_auth_enabled', '0'),
('google_client_id', ''),
('google_client_secret', NULL);

-- Migration: Create admin_login_log table
-- MarketplaceLaundry Admin Security

CREATE TABLE IF NOT EXISTS `admin_login_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempted_identity` VARCHAR(255) NOT NULL COMMENT 'Email atau No. WhatsApp yang dicoba',
  `ip_address` VARCHAR(45) NOT NULL,
  `user_id` INT UNSIGNED NULL COMMENT 'ID user jika akun ditemukan',
  `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 jika sukses, 0 jika gagal',
  `failure_reason` VARCHAR(100) NULL COMMENT 'wrong_password, not_admin, locked, not_found, inactive',
  `user_agent` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin_login_ip_created` (`ip_address`, `created_at`),
  INDEX `idx_admin_login_identity_created` (`attempted_identity`(100), `created_at`),
  INDEX `idx_admin_login_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Migrasi: Tabel Riwayat Percakapan Chatbot AI (ai_chat_messages)
-- Memungkinkan Admin memantau riwayat Tanya AI dari seluruh role
-- ==========================================================

CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `user_name` VARCHAR(150) NULL,
  `user_email` VARCHAR(150) NULL,
  `user_role` VARCHAR(50) NOT NULL DEFAULT 'customer',
  `order_id` BIGINT UNSIGNED NULL,
  `message` TEXT NOT NULL,
  `response` MEDIUMTEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ai_chat_user` (`user_id`),
  INDEX `idx_ai_chat_role` (`user_role`),
  INDEX `idx_ai_chat_order` (`order_id`),
  INDEX `idx_ai_chat_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Install semua database marketplace_laundry selesai.' AS status;
