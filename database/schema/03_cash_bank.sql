-- =====================================================================
-- LJU Finance & Accounting System
-- 03_cash_bank.sql — kas & bank, uang muka (cash advance), biaya
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. TRANSAKSI KAS & BANK (masuk / keluar / transfer)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_transactions` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`                 VARCHAR(50)  NOT NULL,
  `tanggal`               DATE         NOT NULL,
  `periode_id`            INT UNSIGNED NOT NULL,
  `tipe`                  ENUM('masuk','keluar','transfer') NOT NULL,
  `bank_account_id`       INT UNSIGNED NOT NULL,
  `bank_account_tujuan_id` INT UNSIGNED NULL,       -- untuk transfer
  `coa_lawan_id`          INT UNSIGNED NOT NULL,   -- akun lawan (pendapatan/beban/piutang/...)
  `partner_id`            INT UNSIGNED NULL,
  `project_id`            INT UNSIGNED NULL,
  `bidang_id`             INT UNSIGNED NULL,
  `divisi_id`             INT UNSIGNED NULL,
  `cost_center_id`        INT UNSIGNED NULL,
  `jumlah`                DECIMAL(18,2) NOT NULL DEFAULT 0,
  `metode`                ENUM('kas','bank','transfer') NOT NULL DEFAULT 'bank',
  `referensi`             VARCHAR(80)  NULL,
  `deskripsi`             VARCHAR(255) NOT NULL,
  `status`                ENUM('draft','submitted','approved','posted','rejected','void') NOT NULL DEFAULT 'draft',
  `journal_id`            BIGINT UNSIGNED NULL,
  `created_by`            INT UNSIGNED NULL,
  `approved_by`           INT UNSIGNED NULL,
  `approved_at`           DATETIME     NULL,
  `posted_at`             DATETIME     NULL,
  `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cash_nomor` (`nomor`),
  KEY `idx_cash_tanggal` (`tanggal`),
  KEY `idx_cash_status` (`status`),
  KEY `idx_cash_bank` (`bank_account_id`),
  KEY `idx_cash_dimensi` (`bidang_id`, `divisi_id`, `project_id`, `cost_center_id`),
  CONSTRAINT `fk_cash_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cash_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `ms_bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cash_bank_to` FOREIGN KEY (`bank_account_tujuan_id`) REFERENCES `ms_bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cash_coa` FOREIGN KEY (`coa_lawan_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cash_journal` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. UANG MUKA KEGIATAN (cash advance)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_advances` (
  `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`                     VARCHAR(50)  NOT NULL,
  `tanggal`                   DATE         NOT NULL,
  `periode_id`                INT UNSIGNED NOT NULL,
  `pemohon_user_id`           INT UNSIGNED NOT NULL,
  `bagian`                    VARCHAR(100) NULL,
  `bidang_id`                 INT UNSIGNED NULL,
  `divisi_id`                 INT UNSIGNED NULL,
  `cost_center_id`            INT UNSIGNED NULL,
  `project_id`                INT UNSIGNED NULL,
  `keperluan`                 VARCHAR(255) NOT NULL,
  `jumlah_diajukan`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `jumlah_disetujui`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `jumlah_dicairkan`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `jumlah_dipertanggungjawabkan` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `sisa`                      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `tanggal_cair`              DATE NULL,
  `bank_account_id`           INT UNSIGNED NULL,
  `status`                    ENUM('draft','submitted','approved','rejected','cair','pertanggungjawaban',
                                   'selesai','void') NOT NULL DEFAULT 'draft',
  `journal_id`                BIGINT UNSIGNED NULL,   -- jurnal pencairan uang muka
  `journal_settlement_id`     BIGINT UNSIGNED NULL,   -- jurnal pertanggungjawaban
  `created_by`                INT UNSIGNED NULL,
  `approved_by`               INT UNSIGNED NULL,
  `approved_at`               DATETIME     NULL,
  `catatan`                   VARCHAR(255) NULL,
  `created_at`                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adv_nomor` (`nomor`),
  KEY `idx_adv_status` (`status`),
  KEY `idx_adv_pemohon` (`pemohon_user_id`),
  KEY `idx_adv_tanggal` (`tanggal`),
  CONSTRAINT `fk_adv_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_adv_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `ms_bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_adv_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cash_advance_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `advance_id`    BIGINT UNSIGNED NOT NULL,
  `tanggal`       DATE         NULL,
  `keterangan`    VARCHAR(255) NOT NULL,
  `coa_id`        INT UNSIGNED NOT NULL,
  `jumlah`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pajak_type_id` SMALLINT UNSIGNED NULL,
  `nilai_pajak`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `no_bukti`      VARCHAR(80)  NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_advitem_adv` (`advance_id`),
  CONSTRAINT `fk_advitem_adv` FOREIGN KEY (`advance_id`) REFERENCES `cash_advances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_advitem_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. PENGAJUAN BIAYA (expense management)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_requests` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`           VARCHAR(50)  NOT NULL,
  `tanggal`         DATE         NOT NULL,
  `periode_id`      INT UNSIGNED NOT NULL,
  `tipe`            ENUM('operasional','perjalanan_dinas','rapat','pemeliharaan','transportasi','lain') NOT NULL DEFAULT 'operasional',
  `judul`           VARCHAR(150) NOT NULL,
  `pemohon_user_id` INT UNSIGNED NOT NULL,
  `bidang_id`       INT UNSIGNED NULL,
  `divisi_id`       INT UNSIGNED NULL,
  `cost_center_id`  INT UNSIGNED NULL,
  `project_id`      INT UNSIGNED NULL,
  `partner_id`      INT UNSIGNED NULL,
  `bank_account_id` INT UNSIGNED NULL,
  `subtotal`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`             DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pph`             DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`          ENUM('draft','submitted','approved','rejected','dibayar','void') NOT NULL DEFAULT 'draft',
  `journal_id`      BIGINT UNSIGNED NULL,
  `keterangan`      VARCHAR(255) NULL,
  `created_by`      INT UNSIGNED NULL,
  `approved_by`     INT UNSIGNED NULL,
  `approved_at`     DATETIME     NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exp_nomor` (`nomor`),
  KEY `idx_exp_status` (`status`),
  KEY `idx_exp_tipe` (`tipe`),
  KEY `idx_exp_tanggal` (`tanggal`),
  CONSTRAINT `fk_exp_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exp_divisi` FOREIGN KEY (`divisi_id`) REFERENCES `ms_divisi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_id`    BIGINT UNSIGNED NOT NULL,
  `keterangan`    VARCHAR(255) NOT NULL,
  `coa_id`        INT UNSIGNED NOT NULL,
  `jumlah`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pph`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pajak_type_id` SMALLINT UNSIGNED NULL,
  `no_bukti`      VARCHAR(80)  NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expitem_exp` (`expense_id`),
  CONSTRAINT `fk_expitem_exp` FOREIGN KEY (`expense_id`) REFERENCES `expense_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expitem_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. REKONSILIASI BANK
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank_reconciliations` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_account_id` INT UNSIGNED NOT NULL,
  `periode_id`     INT UNSIGNED NOT NULL,
  `tanggal_mulai`  DATE         NOT NULL,
  `tanggal_akhir`  DATE         NOT NULL,
  `saldo_bank`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `saldo_buku`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `selisih`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `jumlah_cocok`   SMALLINT     NOT NULL DEFAULT 0,
  `jumlah_selisih` SMALLINT     NOT NULL DEFAULT 0,
  `status`         ENUM('draft','selesai') NOT NULL DEFAULT 'draft',
  `catatan`        VARCHAR(255) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recon_bank` (`bank_account_id`, `periode_id`),
  CONSTRAINT `fk_recon_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `ms_bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recon_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_reconciliation_items` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reconciliation_id` BIGINT UNSIGNED NOT NULL,
  `tanggal`           DATE         NULL,
  `referensi`         VARCHAR(80)  NULL,
  `keterangan`        VARCHAR(255) NULL,
  `debit`             DECIMAL(18,2) NOT NULL DEFAULT 0,
  `kredit`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `sumber`            ENUM('buku','bank') NOT NULL DEFAULT 'buku',
  `is_matched`        TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_reconitem_recon` (`reconciliation_id`),
  CONSTRAINT `fk_reconitem_recon` FOREIGN KEY (`reconciliation_id`) REFERENCES `bank_reconciliations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
