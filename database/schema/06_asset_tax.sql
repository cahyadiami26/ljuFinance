-- =====================================================================
-- LJU Finance & Accounting System
-- 06_asset_tax.sql — aset tetap & perpajakan
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. ASET TETAP
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `id`                      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`                    VARCHAR(20)  NOT NULL,
  `nama`                    VARCHAR(100) NOT NULL,
  `metode`                  ENUM('garis_lurus','saldo_menurun_ganda','jumlah_angka_tahun','unit_produksi') NOT NULL DEFAULT 'garis_lurus',
  `umur_bulan`              SMALLINT     NOT NULL DEFAULT 60,
  `tarif_tahunan`           DECIMAL(5,2) NOT NULL DEFAULT 0,
  `coa_aset_id`             INT UNSIGNED NULL,
  `coa_akumulasi_id`        INT UNSIGNED NULL,
  `coa_beban_penyusutan_id` INT UNSIGNED NULL,
  `is_active`               TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assetcat_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fixed_assets` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`                 VARCHAR(40)  NOT NULL,       -- kode inventaris
  `nama`                 VARCHAR(150) NOT NULL,
  `category_id`          SMALLINT UNSIGNED NULL,
  `tanggal_perolehan`    DATE         NOT NULL,
  `harga_perolehan`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `nilai_residu`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `umur_bulan`           SMALLINT     NOT NULL DEFAULT 60,
  `akumulasi_penyusutan` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `nilai_buku`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `lokasi`               VARCHAR(150) NULL,
  `penanggung_jawab_user_id` INT UNSIGNED NULL,
  `bidang_id`            INT UNSIGNED NULL,
  `divisi_id`            INT UNSIGNED NULL,
  `project_id`           INT UNSIGNED NULL,
  `no_invoice`           VARCHAR(80)  NULL,
  `status`               ENUM('draft','aktif','dijual','dihapus','nonaktif') NOT NULL DEFAULT 'aktif',
  `journal_id`           BIGINT UNSIGNED NULL,
  `keterangan`           VARCHAR(255) NULL,
  `created_by`           INT UNSIGNED NULL,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_kode` (`kode`),
  KEY `idx_asset_status` (`status`),
  KEY `idx_asset_cat` (`category_id`),
  CONSTRAINT `fk_asset_cat` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_depreciations` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id`     BIGINT UNSIGNED NOT NULL,
  `periode_id`   INT UNSIGNED NOT NULL,
  `tanggal`      DATE         NOT NULL,
  `penyusutan`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `akumulasi`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `nilai_buku`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`       ENUM('draft','posted') NOT NULL DEFAULT 'draft',
  `journal_id`   BIGINT UNSIGNED NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dep_asset_periode` (`asset_id`, `periode_id`),
  KEY `idx_dep_periode` (`periode_id`),
  CONSTRAINT `fk_dep_asset` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dep_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_mutations` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id`    BIGINT UNSIGNED NOT NULL,
  `tipe`        ENUM('perolehan','mutasi_lokasi','penjualan','penghapusan','revaluasi') NOT NULL,
  `tanggal`     DATE         NOT NULL,
  `keterangan`  VARCHAR(255) NULL,
  `nilai`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `dari_lokasi` VARCHAR(150) NULL,
  `ke_lokasi`   VARCHAR(150) NULL,
  `journal_id`  BIGINT UNSIGNED NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mut_asset` (`asset_id`),
  CONSTRAINT `fk_mut_asset` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. PERPAJAKAN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tax_types` (
  `id`               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`             VARCHAR(20)  NOT NULL,          -- PPN, PPH21, PPH23, PPH4-2
  `nama`             VARCHAR(100) NOT NULL,
  `jenis`            ENUM('ppn','pph21','pph22','pph23','pph4_2','pph_final','lain') NOT NULL,
  `tarif`            DECIMAL(5,2) NOT NULL DEFAULT 0,
  `coa_keluaran_id`  INT UNSIGNED NULL,              -- PPN keluaran / hutang pajak
  `coa_masukan_id`   INT UNSIGNED NULL,              -- PPN masukan
  `is_potongan`      TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `keterangan`       VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_documents` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`          VARCHAR(80)  NOT NULL,            -- nomor faktur pajak / bukti potong
  `tipe`           ENUM('faktur_keluaran','faktur_masukan','bukti_potong','ssp','lain') NOT NULL,
  `tax_type_id`    SMALLINT UNSIGNED NOT NULL,
  `partner_id`     INT UNSIGNED NULL,
  `tanggal`        DATE         NOT NULL,
  `periode_id`     INT UNSIGNED NOT NULL,
  `npwp`           VARCHAR(30)  NULL,
  `dpp`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `tarif`          DECIMAL(5,2) NOT NULL DEFAULT 0,
  `nilai_pajak`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ref_module`     VARCHAR(40)  NULL,                -- ap, ar, expense, journal
  `ref_id`         BIGINT UNSIGNED NULL,
  `status`         ENUM('draft','posted','reported','void') NOT NULL DEFAULT 'draft',
  `journal_id`     BIGINT UNSIGNED NULL,
  `keterangan`     VARCHAR(255) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_taxdoc_periode` (`periode_id`, `tipe`),
  KEY `idx_taxdoc_partner` (`partner_id`),
  KEY `idx_taxdoc_type` (`tax_type_id`),
  CONSTRAINT `fk_taxdoc_type` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_taxdoc_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_taxdoc_partner` FOREIGN KEY (`partner_id`) REFERENCES `ms_partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_reports` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id`   INT UNSIGNED NOT NULL,
  `jenis`        ENUM('ppn','pph21','pph23','pph4_2','pph_badan') NOT NULL,
  `total_dpp`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_pajak`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_kredit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `kurang_bayar` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`       ENUM('draft','final','dilaporkan') NOT NULL DEFAULT 'draft',
  `tanggal_lapor` DATE        NULL,
  `referensi`    VARCHAR(80)  NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_taxreport_periode_jenis` (`periode_id`, `jenis`),
  CONSTRAINT `fk_taxreport_periode` FOREIGN KEY (`periode_id`) REFERENCES `fiscal_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
