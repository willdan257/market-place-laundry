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

