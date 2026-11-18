-- ---------------------------------------------------------------------
-- Tahap 7 — pelaporan keuangan & PDF
--
-- Seed `database/seed/01_reference.sql` sudah memuat permission
-- `report.view` / `report.export` untuk seluruh role. Jalankan file ini
-- HANYA bila database dipasang sebelum Tahap 7, agar hak akses laporan
-- dan pengaturan kop/tanda tangan dokumen tersedia di halaman Pengaturan.
--
-- Idempotent: aman dijalankan berulang.
-- ---------------------------------------------------------------------

/* ---------------------------------------------------------------- permission */
INSERT IGNORE INTO `app_role_permissions` (`role_id`, `permission`)
SELECT r.id, m.permission
FROM `app_roles` r
JOIN (
    SELECT 'super_admin' AS role_code, 'report.view' AS permission
    UNION ALL SELECT 'super_admin', 'report.export'
    UNION ALL SELECT 'dirut', 'report.view'
    UNION ALL SELECT 'dirut', 'report.export'
    UNION ALL SELECT 'dirops', 'report.view'
    UNION ALL SELECT 'dirops', 'report.export'
    UNION ALL SELECT 'kabid_keuangan', 'report.view'
    UNION ALL SELECT 'kabid_keuangan', 'report.export'
    UNION ALL SELECT 'kepala_divisi', 'report.view'
    UNION ALL SELECT 'kepala_divisi', 'report.export'
    UNION ALL SELECT 'kepala_bidang', 'report.view'
    UNION ALL SELECT 'kepala_bidang', 'report.export'
    UNION ALL SELECT 'staff_accounting', 'report.view'
    UNION ALL SELECT 'staff_accounting', 'report.export'
    UNION ALL SELECT 'staff_finance', 'report.view'
    UNION ALL SELECT 'staff_finance', 'report.export'
    UNION ALL SELECT 'auditor', 'report.view'
    UNION ALL SELECT 'auditor', 'report.export'
) m ON m.role_code = r.code;

/* ------------------------------------------------------------------- setting */
INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'pdf_paper', 'A4', 'laporan', 'Ukuran kertas PDF laporan (A4/Letter)', 'select', 90
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'pdf_paper');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_comparative_default', '1', 'laporan', 'Tampilkan kolom pembanding periode sebelumnya', 'boolean', 91
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_comparative_default');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_footer_note', 'Laporan ini disusun berdasarkan catatan akuntansi yang tersedia dan belum diaudit.', 'laporan', 'Catatan kaki dokumen laporan', 'textarea', 92
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_footer_note');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign1_label', 'Disiapkan oleh', 'laporan', 'Label tanda tangan kolom 1', 'text', 93
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign1_label');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign1_title', 'Kepala Bagian Keuangan', 'laporan', 'Jabatan tanda tangan kolom 1', 'text', 94
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign1_title');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign2_label', 'Diperiksa oleh', 'laporan', 'Label tanda tangan kolom 2', 'text', 95
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign2_label');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign2_title', 'Manajer Akuntansi', 'laporan', 'Jabatan tanda tangan kolom 2', 'text', 96
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign2_title');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign3_label', 'Disetujui oleh', 'laporan', 'Label tanda tangan kolom 3', 'text', 97
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign3_label');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign3_title', 'Direktur Utama', 'laporan', 'Jabatan tanda tangan kolom 3', 'text', 98
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign3_title');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign1_name', '', 'laporan', 'Nama penanda tangan kolom 1 (kosongkan untuk titik-titik)', 'text', 99
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign1_name');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign2_name', '', 'laporan', 'Nama penanda tangan kolom 2', 'text', 100
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign2_name');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'report_sign3_name', '', 'laporan', 'Nama penanda tangan kolom 3', 'text', 101
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'report_sign3_name');
