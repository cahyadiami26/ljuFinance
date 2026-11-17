-- ---------------------------------------------------------------------
-- Tahap 4 — akun COA pendukung hutang/piutang & pengakuan pendapatan
--
-- Seed `database/seed/01_reference.sql` sudah memuat akun dan prefix ini.
-- Jalankan file ini HANYA bila database sudah terpasang sebelum Tahap 4,
-- agar posting AP/AR dan pengakuan pendapatan tidak gagal karena akun hilang.
--
-- Idempotent: aman dijalankan berulang.
-- ---------------------------------------------------------------------

INSERT INTO `coa_accounts` (`kode`, `nama`, `tipe`, `kategori`, `parent_id`, `level`, `saldo_normal`, `allow_posting`, `is_kas_bank`, `is_terkontrol`, `arus_kas`, `is_active`)
SELECT '1-1330', 'Piutang Belum Ditagih', 'aset', 'aset_lancar',
       (SELECT `id` FROM `coa_accounts` WHERE `kode` = '1-1300' LIMIT 1),
       4, 'D', 1, 0, 1, 'operasi', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `coa_accounts` WHERE `kode` = '1-1330');

INSERT INTO `coa_accounts` (`kode`, `nama`, `tipe`, `kategori`, `parent_id`, `level`, `saldo_normal`, `allow_posting`, `is_kas_bank`, `is_terkontrol`, `arus_kas`, `is_active`)
SELECT '1-1440', 'PPh Dibayar Dimuka', 'aset', 'aset_lancar',
       (SELECT `id` FROM `coa_accounts` WHERE `kode` = '1-1400' LIMIT 1),
       4, 'D', 1, 0, 0, 'operasi', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `coa_accounts` WHERE `kode` = '1-1440');

INSERT IGNORE INTO `doc_sequences` (`code`, `period_key`, `prefix`, `last_number`, `padding`)
VALUES ('REV', DATE_FORMAT(CURDATE(), '%Y'), CONCAT('REV/', DATE_FORMAT(CURDATE(), '%m/%Y'), '/'), 0, 4);
