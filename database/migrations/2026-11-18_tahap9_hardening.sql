-- ---------------------------------------------------------------------
-- Tahap 9 — hardening lanjutan & kesiapan operasional
--
-- Tidak ada tabel baru. File ini menambahkan pengaturan retensi cadangan
-- dan memastikan hard stop anggaran tersedia sebagai pilihan kebijakan.
-- Idempotent: aman dijalankan berulang.
-- ---------------------------------------------------------------------
SET NAMES utf8mb4;

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'backup_retention_days', '14', 'sistem', 'Retensi cadangan database (hari)', 'number', 61
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'backup_retention_days');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'budget_hard_stop', '0', 'anggaran', 'Blokir transaksi bila melebihi pagu', 'boolean', 31
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'budget_hard_stop');
