-- ==============================================================================
-- Migration: Tandai Pesanan COD Selesai (Completed) Menjadi Lunas (Paid)
-- PERINGATAN: Cadangkan (backup) database sebelum menjalankan script ini!
-- ==============================================================================

-- 1. Query untuk memeriksa daftar pesanan COD selesai yang belum lunas:
-- SELECT o.id, o.order_number, o.order_status, o.total_amount, p.id AS payment_id, p.payment_method, p.payment_status, p.paid_at
-- FROM laundry_orders o
-- JOIN payments p ON p.order_id = o.id
-- WHERE o.order_status = 'completed'
--   AND p.payment_method = 'cash'
--   AND p.payment_status != 'paid';

-- 2. Query UPDATE untuk menandai pembayaran menjadi Lunas (paid):
UPDATE payments p
JOIN laundry_orders o ON o.id = p.order_id
SET 
    p.payment_status = 'paid',
    p.paid_at = COALESCE(p.paid_at, o.updated_at, NOW()),
    p.gateway_transaction_id = COALESCE(p.gateway_transaction_id, CONCAT('CASH-', o.id, '-MIGRATED')),
    p.gateway_response = COALESCE(p.gateway_response, '{"method":"cash","note":"marked_paid_migration"}')
WHERE o.order_status = 'completed'
  AND p.payment_method = 'cash'
  AND p.payment_status != 'paid';

