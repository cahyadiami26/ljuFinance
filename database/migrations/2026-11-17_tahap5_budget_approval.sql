-- ---------------------------------------------------------------------
-- Tahap 5 — anggaran (RKAP, komitmen PO) & Approval Center
--
-- Seed `database/seed/01_reference.sql` sudah memuat permission, setting,
-- alur persetujuan, dan prefix dokumen RKAP. Jalankan file ini HANYA bila
-- database sudah terpasang sebelum Tahap 5, agar menu Anggaran dan
-- Approval Center tidak tertolak 403 serta permintaan persetujuan tidak
-- dibuat tanpa alur (approval_flows kosong).
--
-- Idempotent: aman dijalankan berulang.
-- ---------------------------------------------------------------------

/* ---------------------------------------------------------------- permission */
INSERT IGNORE INTO `app_role_permissions` (`role_id`, `permission`)
SELECT r.id, m.permission
FROM `app_roles` r
JOIN (
    SELECT 'super_admin' AS role_code, 'budget.view' AS permission
    UNION ALL SELECT 'super_admin', 'budget.manage'
    UNION ALL SELECT 'super_admin', 'budget.approve'
    UNION ALL SELECT 'super_admin', 'approval.view'
    UNION ALL SELECT 'super_admin', 'approval.act'
    UNION ALL SELECT 'dirut', 'budget.view'
    UNION ALL SELECT 'dirut', 'budget.approve'
    UNION ALL SELECT 'dirut', 'approval.view'
    UNION ALL SELECT 'dirut', 'approval.act'
    UNION ALL SELECT 'dirops', 'budget.view'
    UNION ALL SELECT 'dirops', 'approval.view'
    UNION ALL SELECT 'dirops', 'approval.act'
    UNION ALL SELECT 'kabid_keuangan', 'budget.view'
    UNION ALL SELECT 'kabid_keuangan', 'budget.manage'
    UNION ALL SELECT 'kabid_keuangan', 'budget.approve'
    UNION ALL SELECT 'kabid_keuangan', 'approval.view'
    UNION ALL SELECT 'kabid_keuangan', 'approval.act'
    UNION ALL SELECT 'kepala_divisi', 'budget.view'
    UNION ALL SELECT 'kepala_divisi', 'budget.manage'
    UNION ALL SELECT 'kepala_divisi', 'approval.view'
    UNION ALL SELECT 'kepala_bidang', 'budget.view'
    UNION ALL SELECT 'kepala_bidang', 'budget.manage'
    UNION ALL SELECT 'kepala_bidang', 'budget.approve'
    UNION ALL SELECT 'kepala_bidang', 'approval.view'
    UNION ALL SELECT 'kepala_bidang', 'approval.act'
    UNION ALL SELECT 'staff_accounting', 'budget.view'
    UNION ALL SELECT 'staff_accounting', 'approval.view'
    UNION ALL SELECT 'staff_finance', 'budget.view'
    UNION ALL SELECT 'staff_finance', 'approval.view'
) m ON m.role_code = r.code;

/* ------------------------------------------------------------------- setting */
INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'budget_alert_percent', '80', 'anggaran', 'Ambang peringatan anggaran (%)', 'number', 30
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'budget_alert_percent');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'budget_hard_stop', '0', 'anggaran', 'Blokir transaksi bila melebihi pagu', 'boolean', 31
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'budget_hard_stop');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `group_name`, `label`, `input_type`, `sort_order`)
SELECT 'approval_sla_hours', '24', 'approval', 'SLA persetujuan (jam)', 'number', 40
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'approval_sla_hours');

/* ----------------------------------------------------------- nomor dokumen */
INSERT IGNORE INTO `doc_sequences` (`code`, `period_key`, `prefix`, `last_number`, `padding`)
VALUES ('RKAP', DATE_FORMAT(CURDATE(), '%Y'), CONCAT('RKAP/', DATE_FORMAT(CURDATE(), '%Y'), '/'), 0, 2);

/* ------------------------------------------------------- alur persetujuan RKAP */
INSERT IGNORE INTO `approval_flows`
    (`kode`, `nama`, `module`, `min_amount`, `max_amount`, `bidang_id`, `divisi_id`, `level`, `approver_role_id`, `sla_jam`, `is_active`)
SELECT 'AF-BUD-1', 'RKAP Tahunan', 'budget', 0, 0, NULL, NULL, 1,
       (SELECT `id` FROM `app_roles` WHERE `code` = 'kabid_keuangan' LIMIT 1), 72, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `approval_flows` WHERE `kode` = 'AF-BUD-1');

INSERT IGNORE INTO `approval_flows`
    (`kode`, `nama`, `module`, `min_amount`, `max_amount`, `bidang_id`, `divisi_id`, `level`, `approver_role_id`, `sla_jam`, `is_active`)
SELECT 'AF-BUD-2', 'RKAP Tahunan (final)', 'budget', 0, 0, NULL, NULL, 2,
       (SELECT `id` FROM `app_roles` WHERE `code` = 'dirut' LIMIT 1), 72, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `approval_flows` WHERE `kode` = 'AF-BUD-2');

INSERT IGNORE INTO `approval_flows`
    (`kode`, `nama`, `module`, `min_amount`, `max_amount`, `bidang_id`, `divisi_id`, `level`, `approver_role_id`, `sla_jam`, `is_active`)
SELECT 'AF-BUD-3', 'Revisi RKAP', 'budget', 0, 0, NULL, NULL, 1,
       (SELECT `id` FROM `app_roles` WHERE `code` = 'kepala_bidang' LIMIT 1), 72, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `approval_flows` WHERE `kode` = 'AF-BUD-3');
