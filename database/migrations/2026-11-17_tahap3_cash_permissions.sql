-- ---------------------------------------------------------------------
-- Tahap 3 — permission persetujuan & posting kas/bank
--
-- Seed `database/seed/01_reference.sql` sudah memuat permission ini.
-- Jalankan file ini HANYA bila database sudah terpasang sebelum Tahap 3,
-- agar rute persetujuan/posting transaksi kas tidak tertolak (403).
--
-- Idempotent: aman dijalankan berulang.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `app_role_permissions` (`role_id`, `permission`)
SELECT r.id, m.permission
FROM `app_roles` r
JOIN (
    SELECT 'super_admin'    AS role_code, 'cash.approve' AS permission
    UNION ALL SELECT 'super_admin', 'cash.post'
    UNION ALL SELECT 'dirut', 'cash.approve'
    UNION ALL SELECT 'dirops', 'cash.approve'
    UNION ALL SELECT 'kabid_keuangan', 'cash.approve'
    UNION ALL SELECT 'kabid_keuangan', 'cash.post'
    UNION ALL SELECT 'staff_accounting', 'cash.post'
    UNION ALL SELECT 'staff_finance', 'cash.post'
) m ON m.role_code = r.code;
