-- =====================================================================
-- LJU Finance & Accounting System
-- 01_foundation.sql — fondasi aplikasi + master data organisasi
-- MySQL 5.7+ / MariaDB 10.3+ · InnoDB · utf8mb4
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. PENGATURAN APLIKASI
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key`   VARCHAR(80)  NOT NULL,
  `setting_value` TEXT         NULL,
  `group_name`    VARCHAR(40)  NOT NULL DEFAULT 'umum',
  `label`         VARCHAR(120) NULL,
  `input_type`    ENUM('text','textarea','number','select','boolean','date') NOT NULL DEFAULT 'text',
  `options`       VARCHAR(255) NULL,
  `sort_order`    SMALLINT     NOT NULL DEFAULT 0,
  `updated_by`    INT UNSIGNED NULL,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `idx_settings_group` (`group_name`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. PENGGUNA, ROLE & PERMISSION
--    app_users = proyeksi lokal tabel users milik SSO (database terpisah)
--    nomenklatur: users.division -> nama_bidang, users.department -> nama_divisi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_users` (
  `id`            INT UNSIGNED NOT NULL,
  `nik`           VARCHAR(50)  NULL,
  `name`          VARCHAR(100) NOT NULL,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(100) NULL,
  `position`      VARCHAR(60)  NULL,
  `nama_bidang`   VARCHAR(60)  NULL,
  `nama_divisi`   VARCHAR(60)  NULL,
  `sso_role`      VARCHAR(20)  NULL,
  `avatar`        VARCHAR(255) NULL,
  `photo`         VARCHAR(255) NULL,
  `signature`     VARCHAR(255) NULL,
  `bidang_id`     INT UNSIGNED NULL,
  `divisi_id`     INT UNSIGNED NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     NULL,
  `synced_at`     DATETIME     NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_active` (`is_active`),
  KEY `idx_users_bidang` (`bidang_id`),
  KEY `idx_users_divisi` (`divisi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_roles` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(80)  NOT NULL,
  `description` VARCHAR(255) NULL,
  `level`       TINYINT      NOT NULL DEFAULT 5,
  `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_role_permissions` (
  `role_id`    SMALLINT UNSIGNED NOT NULL,
  `permission` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`role_id`, `permission`),
  KEY `idx_rp_permission` (`permission`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `app_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_user_roles` (
  `user_id`     INT UNSIGNED      NOT NULL,
  `role_id`     SMALLINT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED      NULL,
  `assigned_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `app_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. AUDIT TRAIL, NOMOR DOKUMEN, NOTIFIKASI, LAMPIRAN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NULL,
  `user_name`    VARCHAR(100) NULL,
  `action`       VARCHAR(30)  NOT NULL,           -- create|update|delete|approve|reject|post|reverse|login|close|export
  `module`       VARCHAR(60)  NOT NULL,           -- master.coa, journal, ap, ar, ...
  `ref_table`    VARCHAR(80)  NULL,
  `ref_id`       BIGINT UNSIGNED NULL,
  `ref_code`     VARCHAR(80)  NULL,               -- nomor dokumen / kode yang mudah dibaca
  `description`  VARCHAR(255) NULL,
  `old_values`   LONGTEXT     NULL,
  `new_values`   LONGTEXT     NULL,
  `ip_address`   VARCHAR(45)  NULL,
  `user_agent`   VARCHAR(255) NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_module` (`module`, `created_at`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_ref` (`ref_table`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doc_sequences` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(20)  NOT NULL,            -- JU, KK, KM, CA, AP, AR, RKAP, ...
  `period_key`  VARCHAR(10)  NOT NULL,            -- contoh 2025 atau 2025-10
  `prefix`      VARCHAR(40)  NOT NULL,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `padding`     TINYINT      NOT NULL DEFAULT 4,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seq_code_period` (`code`, `period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(30)  NOT NULL DEFAULT 'info',   -- info|approval|warning|success
  `title`      VARCHAR(150) NOT NULL,
  `body`       VARCHAR(500) NULL,
  `link`       VARCHAR(255) NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attachments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module`      VARCHAR(60)  NOT NULL,
  `ref_id`      BIGINT UNSIGNED NOT NULL,
  `file_name`   VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path`   VARCHAR(255) NOT NULL,
  `mime_type`   VARCHAR(100) NULL,
  `file_size`   INT UNSIGNED NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_att_ref` (`module`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. MASTER ORGANISASI: UNIT USAHA -> BIDANG -> DIVISI -> COST CENTER
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ms_business_units` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`       VARCHAR(20)  NOT NULL,
  `nama`       VARCHAR(100) NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bu_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_bidang` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`           VARCHAR(20)  NOT NULL,
  `nama`           VARCHAR(100) NOT NULL,
  `nama_sso`       VARCHAR(60)  NULL,               -- padanan users.division
  `business_unit_id` INT UNSIGNED NULL,
  `kepala_user_id` INT UNSIGNED NULL,
  `keterangan`     VARCHAR(255) NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bidang_kode` (`kode`),
  KEY `idx_bidang_bu` (`business_unit_id`),
  CONSTRAINT `fk_bidang_bu` FOREIGN KEY (`business_unit_id`) REFERENCES `ms_business_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_divisi` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bidang_id`      INT UNSIGNED NOT NULL,
  `kode`           VARCHAR(20)  NOT NULL,
  `nama`           VARCHAR(100) NOT NULL,
  `nama_sso`       VARCHAR(60)  NULL,               -- padanan users.department
  `kepala_user_id` INT UNSIGNED NULL,
  `keterangan`     VARCHAR(255) NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_divisi_kode` (`kode`),
  KEY `idx_divisi_bidang` (`bidang_id`),
  CONSTRAINT `fk_divisi_bidang` FOREIGN KEY (`bidang_id`) REFERENCES `ms_bidang` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_cost_centers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`            VARCHAR(20)  NOT NULL,
  `nama`            VARCHAR(100) NOT NULL,
  `bidang_id`       INT UNSIGNED NULL,
  `divisi_id`       INT UNSIGNED NULL,
  `business_unit_id` INT UNSIGNED NULL,
  `tipe`            ENUM('cost','profit','investasi') NOT NULL DEFAULT 'cost',
  `penanggung_jawab_user_id` INT UNSIGNED NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_kode` (`kode`),
  KEY `idx_cc_divisi` (`divisi_id`),
  KEY `idx_cc_bidang` (`bidang_id`),
  CONSTRAINT `fk_cc_bidang` FOREIGN KEY (`bidang_id`) REFERENCES `ms_bidang` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cc_divisi` FOREIGN KEY (`divisi_id`) REFERENCES `ms_divisi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. VENDOR / CUSTOMER (partner) & PROYEK / KONTRAK
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ms_partners` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`           VARCHAR(20)  NOT NULL,
  `nama`           VARCHAR(150) NOT NULL,
  `tipe`           ENUM('vendor','customer','both') NOT NULL DEFAULT 'vendor',
  `npwp`           VARCHAR(30)  NULL,
  `alamat`         VARCHAR(255) NULL,
  `kota`           VARCHAR(80)  NULL,
  `telepon`        VARCHAR(40)  NULL,
  `email`          VARCHAR(100) NULL,
  `contact_person` VARCHAR(100) NULL,
  `termin_hari`    SMALLINT     NOT NULL DEFAULT 30,
  `is_pkp`         TINYINT(1)   NOT NULL DEFAULT 1,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `keterangan`     VARCHAR(255) NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partner_kode` (`kode`),
  KEY `idx_partner_tipe` (`tipe`, `is_active`),
  KEY `idx_partner_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_projects` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`             VARCHAR(30)  NOT NULL,
  `nama`             VARCHAR(150) NOT NULL,
  `partner_id`       INT UNSIGNED NULL,             -- pemberi kerja / owner
  `business_unit_id` INT UNSIGNED NULL,
  `bidang_id`        INT UNSIGNED NULL,
  `divisi_id`        INT UNSIGNED NULL,
  `cost_center_id`   INT UNSIGNED NULL,
  `nilai_kontrak`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `uang_muka_persen` DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `tanggal_mulai`    DATE NULL,
  `tanggal_selesai`  DATE NULL,
  `status`           ENUM('perencanaan','berjalan','selesai','batal') NOT NULL DEFAULT 'perencanaan',
  `manager_user_id`  INT UNSIGNED NULL,
  `keterangan`       VARCHAR(255) NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_kode` (`kode`),
  KEY `idx_project_status` (`status`),
  KEY `idx_project_partner` (`partner_id`),
  CONSTRAINT `fk_project_partner` FOREIGN KEY (`partner_id`) REFERENCES `ms_partners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_project_bu` FOREIGN KEY (`business_unit_id`) REFERENCES `ms_business_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_project_cc` FOREIGN KEY (`cost_center_id`) REFERENCES `ms_cost_centers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_contracts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`         VARCHAR(50)  NOT NULL,
  `judul`         VARCHAR(150) NOT NULL,
  `partner_id`    INT UNSIGNED NULL,
  `project_id`    INT UNSIGNED NULL,
  `nilai_kontrak` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `tanggal`       DATE NULL,
  `tanggal_mulai` DATE NULL,
  `tanggal_akhir` DATE NULL,
  `ppn_persen`    DECIMAL(5,2) NOT NULL DEFAULT 11,
  `status`        ENUM('draft','aktif','selesai','batal') NOT NULL DEFAULT 'draft',
  `keterangan`    VARCHAR(255) NULL,
  `created_by`    INT UNSIGNED NULL,
  `updated_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_nomor` (`nomor`),
  KEY `idx_contract_project` (`project_id`),
  CONSTRAINT `fk_contract_project` FOREIGN KEY (`project_id`) REFERENCES `ms_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_contract_terms` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` INT UNSIGNED NOT NULL,
  `termin_ke`   SMALLINT     NOT NULL DEFAULT 1,
  `deskripsi`   VARCHAR(150) NULL,
  `persentase`  DECIMAL(5,2) NOT NULL DEFAULT 0,
  `nilai`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `status`      ENUM('belum','diajukan','ditagih','dibayar') NOT NULL DEFAULT 'belum',
  PRIMARY KEY (`id`),
  KEY `idx_term_contract` (`contract_id`),
  CONSTRAINT `fk_term_contract` FOREIGN KEY (`contract_id`) REFERENCES `ms_contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. REKENING BANK / KAS
--    coa_account_id sengaja tanpa FK (coa_accounts ada di 02_accounting.sql)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ms_bank_accounts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`            VARCHAR(20)  NOT NULL,
  `nama`            VARCHAR(100) NOT NULL,
  `tipe`            ENUM('bank','kas','petty_cash','escrow') NOT NULL DEFAULT 'bank',
  `nama_bank`       VARCHAR(60)  NULL,
  `nomor_rekening`  VARCHAR(50)  NULL,
  `atas_nama`       VARCHAR(100) NULL,
  `coa_account_id`  INT UNSIGNED NULL,
  `mata_uang`       CHAR(3)      NOT NULL DEFAULT 'IDR',
  `saldo_awal`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `saldo_berjalan`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `saldo_minimum`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `catatan`         VARCHAR(255) NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_kode` (`kode`),
  KEY `idx_bank_tipe` (`tipe`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. PERIODE AKUNTANSI
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fiscal_years` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tahun`         SMALLINT     NOT NULL,
  `tanggal_mulai` DATE         NOT NULL,
  `tanggal_selesai` DATE       NOT NULL,
  `status`        ENUM('open','closed','locked') NOT NULL DEFAULT 'open',
  `closed_by`     INT UNSIGNED NULL,
  `closed_at`     DATETIME     NULL,
  `catatan`       VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fy_tahun` (`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fiscal_periods` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tahun`           SMALLINT     NOT NULL,
  `bulan`           TINYINT      NOT NULL,
  `kode`            VARCHAR(10)  NOT NULL,          -- 2025-10
  `nama`            VARCHAR(30)  NOT NULL,          -- Oktober 2025
  `tanggal_mulai`   DATE         NOT NULL,
  `tanggal_selesai` DATE         NOT NULL,
  `status`          ENUM('open','closed','locked') NOT NULL DEFAULT 'open',
  `closed_by`       INT UNSIGNED NULL,
  `closed_at`       DATETIME     NULL,
  `catatan`         VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_periode_kode` (`kode`),
  KEY `idx_periode_tahun` (`tahun`, `bulan`),
  KEY `idx_periode_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
