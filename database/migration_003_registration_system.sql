-- ======================================================
-- MIGRATION 003: User Registration & Multi-Role Partner/Courier Schema
-- Database: marketplace_laundry
-- ======================================================

USE `marketplace_laundry`;

-- 1. Modify users table: add status, email_verified_at, and extend role enum
ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('customer', 'owner', 'driver', 'admin', 'pelanggan', 'mitra', 'kurir') NOT NULL DEFAULT 'pelanggan';

-- Check if status column already exists before adding
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'status');
SET @sql = IF(@col_exists = 0, 
    "ALTER TABLE `users` ADD COLUMN `status` ENUM('aktif', 'menunggu_verifikasi', 'ditolak', 'nonaktif') NOT NULL DEFAULT 'aktif' AFTER `role`", 
    "SELECT 'status already exists'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check if email_verified_at column exists
SET @col_exists2 = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'email_verified_at');
SET @sql2 = IF(@col_exists2 = 0, 
    "ALTER TABLE `users` ADD COLUMN `email_verified_at` TIMESTAMP NULL AFTER `status`", 
    "SELECT 'email_verified_at already exists'");
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- 2. Create customers table
CREATE TABLE IF NOT EXISTS `customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `alamat_utama` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_customers_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create partners table
CREATE TABLE IF NOT EXISTS `partners` (
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

-- 4. Create couriers table
CREATE TABLE IF NOT EXISTS `couriers` (
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

-- 5. Create partner_services table
CREATE TABLE IF NOT EXISTS `partner_services` (
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
