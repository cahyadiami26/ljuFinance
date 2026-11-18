-- ---------------------------------------------------------------------
-- Tahap 6 — aset tetap & perpajakan
--
-- Seed `database/seed/01_reference.sql` sudah memuat permission aset/pajak,
-- kategori aset, jenis pajak, dan prefix dokumen AST/DEP/TAX. Jalankan file
-- ini HANYA bila database sudah terpasang sebelum Tahap 6, agar menu Aset
-- Tetap dan Perpajakan tidak tertolak 403 serta pengaturan penyusutan/pajak
-- tersedia di halaman Pengaturan.
--
-- Idempotent: aman dijalankan berulang.
-- ---------------------------------------------------------------------

/* ---------------------------------------------------------------- permission */
INSERT IGNORE INTO `app_role_permissions` (`role_id`, `permission`)
SELECT r.id, m.permission
FROM `app_roles` r
JOIN (
    SELECT 'super_admin' AS role_code, 'asset.view' AS permission
    UNION ALL SELECT 'super_admin', 'asset.manage'
    UNION ALL SELECT 'super_admin', 'tax.view'
    UNION ALL SELECT 'super_admin', 'tax.manage'
    UNION ALL SELECT 'dirut', 'asset.view'
    UNION ALL SELECT 'dirut', 'tax.view'
    UNION ALL SELECT 'dirops', 'asset.view'
    UNION ALL SELECT 'dirops', 'tax.view'
    UNION ALL SELECT 'kabid_keuangan', 'asset.view'
    UNION ALL SELECT 'kabid_keuangan', 'asset.manage'
    UNION ALL SELECT 'kabid_keuangan', 'tax.view'
    UNION ALL SELECT 'kabid_keuangan', 'tax.manage'
    UNION ALL SELECT 'kepala_divisi', 'asset.view'
    UNION ALL SELECT 'kepala_divisi', 'tax.view'
    UNION ALL SELECT 'kepala_bidang', 'asset.view'
    UNION ALL SELECT 'kepala_bidang', 'tax.view'
    UNION ALL SELECT 'staff_accounting', 'asset.view'
    UNION ALL SELECT 'staff_accounting', 'asset.manage'
    UNION ALL SELECT 'staff_accounting', 'tax.view'
    UNION ALL SELECT 'staff_accounting', 'tax.manage'
    UNION ALL SELECT 'staff_finance', 'asset.view'
    UNION ALL SELECT 'staff_finance', 'tax.view'
    UNION ALL SELECT 'auditor', 'asset.view'
    UNION ALL SELECT 'auditor', 'tax.view'
) m ON m.role_code = r.code;

/* ------------------------------------------------------------------- setting */
INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'asset_auto_depreciate', '0', 'aset', 'Proses penyusutan otomatis saat periode dibuka', 'boolean', 70
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'asset_auto_depreciate');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'asset_depreciation_day', '25', 'aset', 'Tanggal proses penyusutan bulanan', 'number', 71
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'asset_depreciation_day');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'asset_min_capitalize', '1000000', 'aset', 'Batas minimum kapitalisasi aset (Rp)', 'number', 72
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'asset_min_capitalize');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'tax_ppn_rate', '11', 'pajak', 'Tarif PPN berlaku (%)', 'number', 80
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'tax_ppn_rate');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'tax_filing_due_day', '20', 'pajak', 'Batas tanggal pelaporan SPT masa', 'number', 81
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'tax_filing_due_day');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'tax_efaktur_semicolon', '1', 'pajak', 'Gunakan pemisah titik koma pada CSV e-Faktur', 'boolean', 82
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'tax_efaktur_semicolon');

/* ----------------------------------------------------------- nomor dokumen */
INSERT IGNORE INTO `doc_sequences` (`code`, `period_key`, `prefix`, `last_number`, `padding`)
VALUES
    ('AST', DATE_FORMAT(CURDATE(), '%Y'), CONCAT('AST/', DATE_FORMAT(CURDATE(), '%Y'), '/'), 0, 4),
    ('DEP', DATE_FORMAT(CURDATE(), '%Y'), CONCAT('DEP/', DATE_FORMAT(CURDATE(), '%m/%Y'), '/'), 0, 4),
    ('TAX', DATE_FORMAT(CURDATE(), '%Y'), CONCAT('TAX/', DATE_FORMAT(CURDATE(), '%m/%Y'), '/'), 0, 4);

/* ------------------------------------------------------- penanda audit ops */
-- Tidak ada tabel baru: skema aset/pajak sudah ada di database/schema/06_asset_tax.sql.
