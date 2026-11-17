-- =====================================================================
-- LJU Finance & Accounting System
-- 02_accounting.sql — akuntansi inti (COA, jurnal, saldo, tutup buku)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. CHART OF ACCOUNTS (berjenjang)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coa_accounts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`           VARCHAR(20)  NOT NULL,           -- 1-1100
  `nama`           VARCHAR(120) NOT NULL,
  `tipe`           ENUM('aset','kewajiban','ekuitas','pendapatan','beban') NOT NULL,
  `kategori`       ENUM('aset_lancar','aset_tetap','aset_lain','kewajiban_lancar','kewajiban_panjang',
                        'ekuitas','pendapatan_usaha','pendapatan_lain','harga_pokok','beban_operasional',
                        'beban_lain','pajak') NOT NULL DEFAULT 'aset_lancar',
  `parent_id`      INT UNSIGNED NULL,
  `level`          TINYINT      NOT NULL DEFAULT 1,
  `saldo_normal`   ENUM('D','K') NOT NULL DEFAULT 'D',
  `allow_posting`  TINYINT(1)   NOT NULL DEFAULT 1, -- 0 = akun header (tidak bisa dijurnal)
  `is_kas_bank`    TINYINT(1)   NOT NULL DEFAULT 0,
  `is_terkontrol`  TINYINT(1)   NOT NULL DEFAULT 0, -- dikelola modul lain (kas/bank, AR, AP, aset)
  `arus_kas`       ENUM('operasi','investasi','pendanaan','non_kas') NOT NULL DEFAULT 'operasi',
  `deskripsi`      VARCHAR(255) NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coa_kode` (`kode`),
  KEY `idx_coa_tipe` (`tipe`, `kategori`),
  KEY `idx_coa_parent` (`parent_id`),
  KEY `idx_coa_active` (`is_active`),
  CONSTRAINT `fk_coa_parent` FOREIGN KEY (`parent_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. JURNAL (header) — immutable ketika sudah posted
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`            VARCHAR(50)  NOT NULL,
  `tanggal`          DATE         NOT NULL,
  `periode_id`       INT UNSIGNED NOT NULL,
  `fiscal_year`      SMALLINT     NOT NULL,
  `tipe`             ENUM('umum','penerimaan','pengeluaran','transfer','penyesuaian','akrual','pembalik',
                          'penutup','pembukaan','otomatis') NOT NULL DEFAULT 'umum',
  `sumber`           ENUM('manual','kas_bank','uang_muka','biaya','ap','ar','aset','pajak','penyesuaian',
                          'penutup','pembalik','integrasi') NOT NULL DEFAULT 'manual',
  `referensi`        VARCHAR(80)  NULL,             -- nomor dokumen modul asal
  `deskripsi`        VARCHAR(255) NOT NULL,
  `total_debit`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_kredit`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`           ENUM('draft','submitted','approved','posted','rejected','reversed') NOT NULL DEFAULT 'draft',
  `created_by`       INT UNSIGNED NULL,
  `submitted_at`     DATETIME     NULL,
  `approved_by`      INT UNSIGNED NULL,
  `approved_at`      DATETIME     NULL,
  `posted_by`        INT UNSIGNED NULL,
  `posted_at`        DATETIME     NULL,
  `rejected_by`      INT UNSIGNED NULL,
  `rejected_at`      DATETIME     NULL,
  `rejection_reason` VARCHAR(255) NULL,
  `reversal_of_id`   BIGINT UNSIGNED NULL,          -- jurnal ini membalik jurnal X
  `reversed_by_id`   BIGINT UNSIGNED NULL,          -- jurnal ini sudah dibalik oleh jurnal Y
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journal_nomor` (`nomor`),
  KEY `idx_journal_tanggal` (`tanggal`),
  KEY `idx_journal_periode` (`periode_id`, `status`),
  KEY `idx_journal_status` (`status`),
  KEY `idx_journal_sumber` (`sumber`),
  KEY `idx_journal_ref` (`referensi`),
  CONSTRAINT `fk_journal_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_journal_reversal` FOREIGN KEY (`reversal_of_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. JURNAL (baris) — memuat seluruh dimensi pelaporan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `journal_lines` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_id`       BIGINT UNSIGNED NOT NULL,
  `urutan`           SMALLINT     NOT NULL DEFAULT 1,
  `coa_id`           INT UNSIGNED NOT NULL,
  `deskripsi`        VARCHAR(255) NULL,
  `debit`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `kredit`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `bidang_id`        INT UNSIGNED NULL,
  `divisi_id`        INT UNSIGNED NULL,
  `business_unit_id` INT UNSIGNED NULL,
  `project_id`       INT UNSIGNED NULL,
  `cost_center_id`   INT UNSIGNED NULL,
  `partner_id`       INT UNSIGNED NULL,
  `tax_type_id`      SMALLINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_line_journal` (`journal_id`),
  KEY `idx_line_coa` (`coa_id`),
  KEY `idx_line_dimensi` (`bidang_id`, `divisi_id`, `project_id`, `cost_center_id`),
  CONSTRAINT `fk_line_journal` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_line_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_line_bidang` FOREIGN KEY (`bidang_id`) REFERENCES `ms_bidang` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_line_divisi` FOREIGN KEY (`divisi_id`) REFERENCES `ms_divisi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_line_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_line_cc` FOREIGN KEY (`cost_center_id`) REFERENCES `ms_cost_centers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. SALDO PER AKUN PER PERIODE (hasil posting, sumber laporan cepat)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_period_balances` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coa_id`          INT UNSIGNED NOT NULL,
  `periode_id`      INT UNSIGNED NOT NULL,
  `saldo_awal`      DECIMAL(18,2) NOT NULL DEFAULT 0,  -- posisi normal (D untuk aset/beban)
  `mutasi_debit`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `mutasi_kredit`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `saldo_akhir`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_balance_coa_periode` (`coa_id`, `periode_id`),
  KEY `idx_balance_periode` (`periode_id`),
  CONSTRAINT `fk_balance_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_balance_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. RIWAYAT TUTUP BUKU
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `period_closings` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id`  INT UNSIGNED NOT NULL,
  `tipe`        ENUM('bulanan','tahunan') NOT NULL DEFAULT 'bulanan',
  `journal_id`  BIGINT UNSIGNED NULL,
  `total_debit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_kredit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `laba_rugi`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `catatan`     VARCHAR(255) NULL,
  `executed_by` INT UNSIGNED NULL,
  `executed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_closing_periode` (`periode_id`),
  CONSTRAINT `fk_closing_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
