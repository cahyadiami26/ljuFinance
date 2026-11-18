-- =====================================================================
-- LJU Finance & Accounting System
-- 08_integration.sql — pengadaan, persediaan, dan integrasi proyek
-- MySQL 5.7+ / MariaDB 10.3+ · InnoDB · utf8mb4
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. GUDANG & PERSEDIAAN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warehouses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`          VARCHAR(20)  NOT NULL,
  `nama`          VARCHAR(100) NOT NULL,
  `lokasi`        VARCHAR(150) NULL,
  `penanggung_jawab` VARCHAR(100) NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_warehouse_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`               VARCHAR(30)  NOT NULL,
  `nama`               VARCHAR(150) NOT NULL,
  `kategori`           VARCHAR(60)  NOT NULL DEFAULT 'material',
  `satuan`             VARCHAR(20)  NOT NULL DEFAULT 'unit',
  `coa_persediaan_id`  INT UNSIGNED NOT NULL,
  `coa_beban_id`       INT UNSIGNED NULL,
  `harga_rata`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `stok`               DECIMAL(18,4) NOT NULL DEFAULT 0,
  `stok_min`           DECIMAL(18,4) NOT NULL DEFAULT 0,
  `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_kode` (`kode`),
  KEY `idx_inventory_kategori` (`kategori`),
  CONSTRAINT `fk_inventory_coa` FOREIGN KEY (`coa_persediaan_id`) REFERENCES `coa_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_inventory_beban` FOREIGN KEY (`coa_beban_id`) REFERENCES `coa_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`         VARCHAR(50)   NOT NULL,
  `item_id`       INT UNSIGNED  NOT NULL,
  `warehouse_id`  INT UNSIGNED  NULL,
  `tanggal`       DATE          NOT NULL,
  `tipe`          ENUM('masuk','keluar','penyesuaian') NOT NULL DEFAULT 'masuk',
  `qty`           DECIMAL(18,4) NOT NULL DEFAULT 0,
  `harga`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `nilai`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `stok_setelah`  DECIMAL(18,4) NOT NULL DEFAULT 0,
  `ref_module`    VARCHAR(30)   NULL,
  `ref_id`        BIGINT UNSIGNED NULL,
  `project_id`    INT UNSIGNED  NULL,
  `keterangan`    VARCHAR(255)  NULL,
  `journal_id`    BIGINT UNSIGNED NULL,
  `created_by`    INT UNSIGNED  NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_nomor` (`nomor`),
  KEY `idx_inventory_item_date` (`item_id`, `tanggal`),
  KEY `idx_inventory_ref` (`ref_module`, `ref_id`),
  CONSTRAINT `fk_inv_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_inv_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_journal` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. PURCHASE ORDER & PENERIMAAN BARANG
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`           VARCHAR(50)   NOT NULL,
  `tanggal`         DATE          NOT NULL,
  `partner_id`      INT UNSIGNED  NOT NULL,
  `project_id`      INT UNSIGNED  NULL,
  `bidang_id`       INT UNSIGNED  NULL,
  `divisi_id`       INT UNSIGNED  NULL,
  `business_unit_id` INT UNSIGNED NULL,
  `cost_center_id`  INT UNSIGNED  NULL,
  `status`          ENUM('draft','diajukan','disetujui','sebagian','selesai','batal') NOT NULL DEFAULT 'draft',
  `subtotal`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn`             DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `termin_hari`     SMALLINT      NOT NULL DEFAULT 30,
  `catatan`         VARCHAR(255)  NULL,
  `ap_invoice_id`   BIGINT UNSIGNED NULL,
  `approved_by`     INT UNSIGNED  NULL,
  `approved_at`     DATETIME      NULL,
  `created_by`      INT UNSIGNED  NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_po_nomor` (`nomor`),
  KEY `idx_po_partner` (`partner_id`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_project` (`project_id`),
  CONSTRAINT `fk_po_partner` FOREIGN KEY (`partner_id`) REFERENCES `ms_partners` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_po_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_ap` FOREIGN KEY (`ap_invoice_id`) REFERENCES `ap_invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id`         BIGINT UNSIGNED NOT NULL,
  `item_id`       INT UNSIGNED  NULL,
  `deskripsi`     VARCHAR(255)  NOT NULL,
  `coa_id`        INT UNSIGNED  NULL,
  `qty`           DECIMAL(18,4) NOT NULL DEFAULT 0,
  `satuan`        VARCHAR(20)   NOT NULL DEFAULT 'unit',
  `harga`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `ppn_persen`    DECIMAL(5,2)  NOT NULL DEFAULT 11,
  `total`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `diterima_qty`  DECIMAL(18,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_poitem_po` (`po_id`),
  CONSTRAINT `fk_poitem_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poitem_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_poitem_coa` FOREIGN KEY (`coa_id`) REFERENCES `coa_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `goods_receipts` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`         VARCHAR(50)   NOT NULL,
  `po_id`         BIGINT UNSIGNED NOT NULL,
  `tanggal`       DATE          NOT NULL,
  `warehouse_id`  INT UNSIGNED  NULL,
  `status`        ENUM('draft','posted','batal') NOT NULL DEFAULT 'draft',
  `catatan`       VARCHAR(255)  NULL,
  `created_by`    INT UNSIGNED  NULL,
  `posted_at`     DATETIME      NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gr_nomor` (`nomor`),
  KEY `idx_gr_po` (`po_id`),
  CONSTRAINT `fk_gr_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gr_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_id`  BIGINT UNSIGNED NOT NULL,
  `po_item_id`  BIGINT UNSIGNED NOT NULL,
  `item_id`     INT UNSIGNED  NULL,
  `qty`         DECIMAL(18,4) NOT NULL DEFAULT 0,
  `harga`       DECIMAL(18,4) NOT NULL DEFAULT 0,
  `total`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_gritem_receipt` (`receipt_id`),
  CONSTRAINT `fk_gritem_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gritem_poitem` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_gritem_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
