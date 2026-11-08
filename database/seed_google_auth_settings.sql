-- ==========================================================
-- Migrasi: Pengaturan Kredensial Google OAuth 2.0
-- Jalankan di phpMyAdmin jika tabel settings sudah dibuat
-- ==========================================================

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('google_auth_enabled', '0'),
('google_client_id', ''),
('google_client_secret', NULL);

