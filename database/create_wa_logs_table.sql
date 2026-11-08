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

