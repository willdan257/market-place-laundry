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

