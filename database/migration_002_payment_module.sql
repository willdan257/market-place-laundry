-- Payment Module DB Migration
-- Run this on existing marketplace_laundry database

USE `marketplace_laundry`;

-- ==========================================
-- MIGRATION 001: Extend payments table
-- ==========================================
ALTER TABLE `payments`
    ADD COLUMN `gateway_transaction_id` VARCHAR(100) NULL AFTER `transaction_reference`,
    ADD COLUMN `gateway_response` JSON NULL AFTER `gateway_transaction_id`,
    ADD COLUMN `idempotency_key` VARCHAR(100) NULL AFTER `gateway_response`,
    ADD COLUMN `payment_type` VARCHAR(50) NULL COMMENT 'e.g. qris, bank_transfer' AFTER `idempotency_key`,
    ADD COLUMN `qris_url` TEXT NULL COMMENT 'QRIS image URL from Midtrans' AFTER `payment_type`,
    ADD COLUMN `qris_string` TEXT NULL COMMENT 'Raw QRIS string for QR generation' AFTER `qris_url`,
    ADD COLUMN `va_number` VARCHAR(50) NULL AFTER `qris_string`,
    ADD COLUMN `confirmed_by` BIGINT UNSIGNED NULL AFTER `va_number`,
    ADD UNIQUE KEY `idx_payment_idempotency` (`idempotency_key`),
    ADD INDEX `idx_payment_gateway_tx` (`gateway_transaction_id`);

-- ==========================================
-- MIGRATION 002: Add waiting_payment to order status ENUM
-- ==========================================
ALTER TABLE `laundry_orders`
    MODIFY COLUMN `order_status` ENUM(
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
    ) NOT NULL DEFAULT 'pending_pickup';

-- ==========================================
-- MIGRATION 003: Add cancelled status to payments
-- ==========================================
ALTER TABLE `payments`
    MODIFY COLUMN `payment_status`
        ENUM('pending', 'paid', 'expired', 'failed', 'cancelled', 'refunded')
        NOT NULL DEFAULT 'pending';

SELECT 'Migration completed successfully.' AS status;
