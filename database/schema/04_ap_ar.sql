-- =====================================================================
-- LJU Finance & Accounting System
-- 04_ap_ar.sql — hutang usaha (AP), piutang usaha (AR), pengakuan pendapatan
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. HUTANG USAHA — TAGIHAN SUPPLIER
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ap_invoices` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`              VARCHAR(50)  NOT NULL,        -- nomor internal AP/2025/10/0001
  `nomor_supplier`     VARCHAR(80)  NULL,            -- nomor invoice dari vendor
  `partner_id`         INT UNSIGNED NOT NULL,
  `tanggal`            DATE         NOT NULL,
  `tanggal_jatuh_tempo` DATE        NOT NULL,
  `termin_hari`        SMALLINT     NOT NULL DEFAULT 30,
  `termin_ke`          SMALLINT     NULL,
  `project_id`         INT UNSIGNED NULL,
  `bidang_id`          INT UNSIGNED NULL,
  `divisi_id`          INT UNSIGNED NULL,
  `cost_center_id`     INT UNSIGNED NULL,
  `mata_uang`          CHAR(3)      NOT NULL DEFAULT 'IDR',
  `subtotal`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`                DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pph`                DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`              DECIMAL(18,2) NOT NULL DEFAULT 0,
  `dibayar`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `sisa`               DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`             ENUM('draft','submitted','approved','posted','partial','paid','overdue','void') NOT NULL DEFAULT 'draft',
  `journal_id`         BIGINT UNSIGNED NULL,
  `keterangan`         VARCHAR(255) NULL,
  `created_by`         INT UNSIGNED NULL,
  `approved_by`        INT UNSIGNED NULL,
  `approved_at`        DATETIME     NULL,
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ap_nomor` (`nomor`),
  KEY `idx_ap_partner` (`partner_id`),
  KEY `idx_ap_status` (`status`),
  KEY `idx_ap_due` (`tanggal_jatuh_tempo`),
  KEY `idx_ap_project` (`project_id`),
  CONSTRAINT `fk_ap_partner` FOREIGN KEY (`partner_id`) REFERENCES `ms_partners` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ap_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_journal` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ap_invoice_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `keterangan` VARCHAR(255) NOT NULL,
  `coa_id`     INT UNSIGNED NOT NULL,
  `jumlah`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pph`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_apitem_invoice` (`invoice_id`),
  CONSTRAINT `fk_apitem_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `ap_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apitem_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ap_payments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`           VARCHAR(50)  NOT NULL,
  `invoice_id`      BIGINT UNSIGNED NOT NULL,
  `tanggal`         DATE         NOT NULL,
  `periode_id`      INT UNSIGNED NOT NULL,
  `bank_account_id` INT UNSIGNED NOT NULL,
  `jumlah`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `potongan_pph`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `metode`          ENUM('transfer','tunai','giro','lain') NOT NULL DEFAULT 'transfer',
  `referensi`       VARCHAR(80)  NULL,
  `status`          ENUM('draft','submitted','approved','posted','void') NOT NULL DEFAULT 'draft',
  `journal_id`      BIGINT UNSIGNED NULL,
  `created_by`      INT UNSIGNED NULL,
  `approved_by`     INT UNSIGNED NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appay_nomor` (`nomor`),
  KEY `idx_appay_invoice` (`invoice_id`),
  KEY `idx_appay_tanggal` (`tanggal`),
  CONSTRAINT `fk_appay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `ap_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appay_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `ms_bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appay_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. PIUTANG USAHA — INVOICE PELANGGAN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ar_invoices` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`              VARCHAR(50)  NOT NULL,
  `partner_id`         INT UNSIGNED NOT NULL,
  `project_id`         INT UNSIGNED NULL,
  `contract_id`        INT UNSIGNED NULL,
  `termin_ke`          SMALLINT     NULL,
  `tanggal`            DATE         NOT NULL,
  `tanggal_jatuh_tempo` DATE        NOT NULL,
  `termin_hari`        SMALLINT     NOT NULL DEFAULT 30,
  `deskripsi`          VARCHAR(255) NULL,
  `bidang_id`          INT UNSIGNED NULL,
  `divisi_id`          INT UNSIGNED NULL,
  `cost_center_id`     INT UNSIGNED NULL,
  `business_unit_id`   INT UNSIGNED NULL,
  `mata_uang`          CHAR(3)      NOT NULL DEFAULT 'IDR',
  `subtotal`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`                DECIMAL(18,2) NOT NULL DEFAULT 0,
  `pph`                DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`              DECIMAL(18,2) NOT NULL DEFAULT 0,
  `dibayar`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `sisa`               DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`             ENUM('draft','submitted','approved','posted','partial','paid','overdue','void') NOT NULL DEFAULT 'draft',
  `journal_id`         BIGINT UNSIGNED NULL,
  `created_by`         INT UNSIGNED NULL,
  `approved_by`        INT UNSIGNED NULL,
  `approved_at`        DATETIME     NULL,
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ar_nomor` (`nomor`),
  KEY `idx_ar_partner` (`partner_id`),
  KEY `idx_ar_status` (`status`),
  KEY `idx_ar_due` (`tanggal_jatuh_tempo`),
  KEY `idx_ar_project` (`project_id`),
  CONSTRAINT `fk_ar_partner` FOREIGN KEY (`partner_id`) REFERENCES `ms_partners` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ar_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_contract` FOREIGN KEY (`contract_id`) REFERENCES `ms_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_journal` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ar_invoice_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `keterangan` VARCHAR(255) NOT NULL,
  `coa_id`     INT UNSIGNED NOT NULL,
  `jumlah`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_aritem_invoice` (`invoice_id`),
  CONSTRAINT `fk_aritem_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `ar_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aritem_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ar_payments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`           VARCHAR(50)  NOT NULL,
  `invoice_id`      BIGINT UNSIGNED NOT NULL,
  `tanggal`         DATE         NOT NULL,
  `periode_id`      INT UNSIGNED NOT NULL,
  `bank_account_id` INT UNSIGNED NOT NULL,
  `jumlah`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `potongan_pph`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `metode`          ENUM('transfer','tunai','giro','lain') NOT NULL DEFAULT 'transfer',
  `referensi`       VARCHAR(80)  NULL,
  `status`          ENUM('draft','submitted','approved','posted','void') NOT NULL DEFAULT 'draft',
  `journal_id`      BIGINT UNSIGNED NULL,
  `created_by`      INT UNSIGNED NULL,
  `approved_by`     INT UNSIGNED NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arpay_nomor` (`nomor`),
  KEY `idx_arpay_invoice` (`invoice_id`),
  KEY `idx_arpay_tanggal` (`tanggal`),
  CONSTRAINT `fk_arpay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `ar_invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_arpay_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `ms_bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_arpay_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. PENGAKUAN PENDAPATAN (revenue recognition per proyek/termin)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `revenue_recognitions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`        VARCHAR(50)  NOT NULL,
  `project_id`   INT UNSIGNED NULL,
  `contract_id`  INT UNSIGNED NULL,
  `periode_id`   INT UNSIGNED NOT NULL,
  `tanggal`      DATE         NOT NULL,
  `persentase`   DECIMAL(5,2) NOT NULL DEFAULT 0,
  `nilai`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `metode`       ENUM('persentase_penyelesaian','termin','kontrak_selesai') NOT NULL DEFAULT 'persentase_penyelesaian',
  `status`       ENUM('draft','submitted','approved','posted','void') NOT NULL DEFAULT 'draft',
  `journal_id`   BIGINT UNSIGNED NULL,
  `keterangan`   VARCHAR(255) NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rev_nomor` (`nomor`),
  KEY `idx_rev_project` (`project_id`, `periode_id`),
  CONSTRAINT `fk_rev_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rev_contract` FOREIGN KEY (`contract_id`) REFERENCES `ms_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rev_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
