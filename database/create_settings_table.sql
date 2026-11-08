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

