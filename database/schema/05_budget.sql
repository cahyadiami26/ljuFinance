-- =====================================================================
-- LJU Finance & Accounting System
-- 05_budget.sql — RKAP, realisasi anggaran, komitmen pengadaan
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `budgets` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`            VARCHAR(50)  NOT NULL,        -- RKAP/2025/01 atau RKAP-REV-04
  `tahun`            SMALLINT     NOT NULL,
  `versi`            SMALLINT     NOT NULL DEFAULT 1,
  `parent_id`        BIGINT UNSIGNED NULL,         -- revisi dari anggaran sebelumnya
  `judul`            VARCHAR(150) NOT NULL,
  `status`           ENUM('draft','submitted','approved','rejected','aktif','arsip') NOT NULL DEFAULT 'draft',
  `total_pendapatan` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_beban`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `catatan`          VARCHAR(255) NULL,
  `created_by`       INT UNSIGNED NULL,
  `approved_by`      INT UNSIGNED NULL,
  `approved_at`      DATETIME     NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_nomor` (`nomor`),
  KEY `idx_budget_tahun` (`tahun`, `status`),
  CONSTRAINT `fk_budget_parent` FOREIGN KEY (`parent_id`) REFERENCES `budgets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `budget_lines` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id`        BIGINT UNSIGNED NOT NULL,
  `coa_id`           INT UNSIGNED NOT NULL,
  `jenis`            ENUM('pendapatan','beban') NOT NULL DEFAULT 'beban',
  `bidang_id`        INT UNSIGNED NULL,
  `divisi_id`        INT UNSIGNED NULL,
  `business_unit_id` INT UNSIGNED NULL,
  `cost_center_id`   INT UNSIGNED NULL,
  `project_id`       INT UNSIGNED NULL,
  `m01` DECIMAL(18,2) NOT NULL DEFAULT 0, `m02` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `m03` DECIMAL(18,2) NOT NULL DEFAULT 0, `m04` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `m05` DECIMAL(18,2) NOT NULL DEFAULT 0, `m06` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `m07` DECIMAL(18,2) NOT NULL DEFAULT 0, `m08` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `m09` DECIMAL(18,2) NOT NULL DEFAULT 0, `m10` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `m11` DECIMAL(18,2) NOT NULL DEFAULT 0, `m12` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `catatan`          VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bline_budget` (`budget_id`),
  KEY `idx_bline_coa` (`coa_id`),
  KEY `idx_bline_dimensi` (`divisi_id`, `bidang_id`, `cost_center_id`, `project_id`),
  CONSTRAINT `fk_bline_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bline_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `budget_commitments` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_po`       VARCHAR(50)  NOT NULL,          -- PO / kontrak pengadaan
  `budget_id`      BIGINT UNSIGNED NULL,
  `partner_id`     INT UNSIGNED NULL,
  `coa_id`         INT UNSIGNED NULL,
  `project_id`     INT UNSIGNED NULL,
  `bidang_id`      INT UNSIGNED NULL,
  `divisi_id`      INT UNSIGNED NULL,
  `cost_center_id` INT UNSIGNED NULL,
  `tanggal`        DATE         NOT NULL,
  `nilai`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`         ENUM('open','sebagian','closed','cancelled') NOT NULL DEFAULT 'open',
  `keterangan`     VARCHAR(255) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commit_po` (`nomor_po`),
  KEY `idx_commit_divisi` (`divisi_id`),
  CONSTRAINT `fk_commit_partner` FOREIGN KEY (`partner_id`) REFERENCES `ms_partners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_commit_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
