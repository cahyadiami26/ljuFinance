-- =====================================================================
-- LJU Finance & Accounting System
-- 07_approval.sql — konfigurasi & riwayat persetujuan (generic engine)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `approval_flows` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`              VARCHAR(40)  NOT NULL,
  `nama`              VARCHAR(120) NOT NULL,
  `module`            ENUM('journal','cash_transaction','cash_advance','expense','ap_invoice','ap_payment',
                           'ar_invoice','budget','asset_disposal','revenue') NOT NULL,
  `min_amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `max_amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,   -- 0 = tanpa batas atas
  `bidang_id`         INT UNSIGNED NULL,                  -- null = semua bidang
  `divisi_id`         INT UNSIGNED NULL,
  `level`             TINYINT      NOT NULL DEFAULT 1,
  `approver_role_id`  SMALLINT UNSIGNED NULL,
  `approver_user_id`  INT UNSIGNED NULL,
  `sla_jam`           SMALLINT     NOT NULL DEFAULT 24,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_flow_kode` (`kode`),
  KEY `idx_flow_module` (`module`, `is_active`),
  CONSTRAINT `fk_flow_role` FOREIGN KEY (`approver_role_id`) REFERENCES `app_roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approval_requests` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module`         ENUM('journal','cash_transaction','cash_advance','expense','ap_invoice','ap_payment',
                        'ar_invoice','budget','asset_disposal','revenue') NOT NULL,
  `ref_id`         BIGINT UNSIGNED NOT NULL,
  `nomor_dokumen`  VARCHAR(50)  NOT NULL,
  `judul`          VARCHAR(200) NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `bidang_id`      INT UNSIGNED NULL,
  `divisi_id`      INT UNSIGNED NULL,
  `project_id`     INT UNSIGNED NULL,
  `requested_by`   INT UNSIGNED NULL,
  `current_level`  TINYINT      NOT NULL DEFAULT 1,
  `max_level`      TINYINT      NOT NULL DEFAULT 1,
  `status`         ENUM('pending','approved','rejected','returned','cancelled','expired') NOT NULL DEFAULT 'pending',
  `deadline`       DATETIME     NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`   DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approval_ref` (`module`, `ref_id`),
  KEY `idx_approval_status` (`status`, `current_level`),
  KEY `idx_approval_requested` (`requested_by`),
  KEY `idx_approval_bidang` (`bidang_id`, `divisi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approval_actions` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id`         BIGINT UNSIGNED NOT NULL,
  `level`              TINYINT      NOT NULL DEFAULT 1,
  `approver_user_id`   INT UNSIGNED NULL,
  `action`             ENUM('approve','reject','return','delegate') NOT NULL,
  `notes`              VARCHAR(255) NULL,
  `delegated_to_user_id` INT UNSIGNED NULL,
  `acted_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action_request` (`request_id`, `acted_at`),
  CONSTRAINT `fk_action_request` FOREIGN KEY (`request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approval_delegations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id`  INT UNSIGNED NOT NULL,
  `to_user_id`    INT UNSIGNED NOT NULL,
  `module`        VARCHAR(40)  NULL,               -- null = semua modul
  `tanggal_mulai` DATE         NOT NULL,
  `tanggal_selesai` DATE       NOT NULL,
  `alasan`        VARCHAR(255) NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deleg_from` (`from_user_id`, `is_active`),
  KEY `idx_deleg_to` (`to_user_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
