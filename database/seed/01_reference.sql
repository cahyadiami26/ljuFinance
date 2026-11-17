-- =====================================================================
-- LJU Finance & Accounting System
-- 01_reference.sql — seed referensi (WAJIB): pengaturan, role & permission,
-- master organisasi, COA, periode, tarif pajak, approval flow, penomoran.
-- Idempotent: aman dijalankan ulang (INSERT IGNORE / ON DUPLICATE KEY).
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. PENGATURAN APLIKASI
-- ---------------------------------------------------------------------
INSERT INTO `app_settings` (`setting_key`,`setting_value`,`group_name`,`label`,`input_type`,`sort_order`) VALUES
('company_name','PT. Lampung Jasa Utama','identitas','Nama perusahaan','text',1),
('company_short','LJU','identitas','Nama singkat','text',2),
('app_name','LJU Finance','identitas','Nama aplikasi','text',3),
('app_subtitle','Finance & Accounting System','identitas','Subjudul aplikasi','text',4),
('company_address','Jl. Soekarno-Hatta No. 88, Bandar Lampung, Lampung 35141','identitas','Alamat','textarea',5),
('company_npwp','01.234.567.8-321.000','identitas','NPWP perusahaan','text',6),
('company_phone','(0721) 123456','identitas','Telepon','text',7),
('company_email','finance@lju.co.id','identitas','Email','text',8),
('logo_url','https://dms.lju.co.id/assets/img/logo.png','identitas','URL logo','text',9),
('avatar_base_url','https://dms.lju.co.id/uploads/avatars/','identitas','Base URL avatar','text',10),
('currency','IDR','keuangan','Mata uang','text',20),
('fiscal_start_month','1','keuangan','Bulan awal tahun buku','number',21),
('budget_alert_percent','80','anggaran','Ambang peringatan anggaran (%)','number',30),
('budget_hard_stop','0','anggaran','Blokir transaksi bila melebihi pagu','boolean',31),
('approval_sla_hours','24','approval','SLA persetujuan (jam)','number',40),
('journal_require_attachment','0','akuntansi','Wajib lampiran bukti pada jurnal','boolean',41),
('lock_after_close','1','akuntansi','Kunci periode setelah tutup buku','boolean',42),
('pdf_paper','A4','laporan','Ukuran kertas PDF','select',50),
('app_version','1.0.0','sistem','Versi aplikasi','text',60)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `group_name` = VALUES(`group_name`);

-- ---------------------------------------------------------------------
-- 2. ROLE & PERMISSION
-- ---------------------------------------------------------------------
INSERT INTO `app_roles` (`id`,`code`,`name`,`description`,`level`,`is_system`) VALUES
(1,'super_admin','Super Administrator','Seluruh konfigurasi sistem',1,1),
(2,'dirut','Direktur Utama','Dashboard, laporan, approval akhir',2,1),
(3,'dirops','Direktur Operasional','Dashboard, laporan, approval operasional',3,1),
(4,'kabid_keuangan','Kepala Bidang Keuangan','Seluruh pengelolaan keuangan',3,1),
(5,'staff_accounting','Staff Accounting','Jurnal, buku besar, laporan',5,1),
(6,'staff_finance','Staff Finance','Kas, bank, hutang, piutang, biaya',5,1),
(7,'kepala_divisi','Kepala Divisi','Pengajuan & monitoring anggaran divisi',4,1),
(8,'kepala_bidang','Kepala Bidang','Pengajuan & monitoring anggaran bidang',4,1),
(9,'auditor','Auditor Internal','Pemeriksaan & audit trail (read-only)',4,1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Super Administrator mendapat seluruh permission
INSERT IGNORE INTO `app_role_permissions` (`role_id`,`permission`)
SELECT 1, p.permission FROM (
  SELECT 'dashboard.view' AS permission UNION ALL SELECT 'report.view' UNION ALL SELECT 'report.export'
  UNION ALL SELECT 'coa.view' UNION ALL SELECT 'coa.manage' UNION ALL SELECT 'master.view' UNION ALL SELECT 'master.manage'
  UNION ALL SELECT 'period.view' UNION ALL SELECT 'period.manage'
  UNION ALL SELECT 'journal.view' UNION ALL SELECT 'journal.create' UNION ALL SELECT 'journal.submit'
  UNION ALL SELECT 'journal.approve' UNION ALL SELECT 'journal.post' UNION ALL SELECT 'journal.reverse'
  UNION ALL SELECT 'cash.view' UNION ALL SELECT 'cash.manage'
  UNION ALL SELECT 'cash.approve' UNION ALL SELECT 'cash.post'
  UNION ALL SELECT 'advance.view' UNION ALL SELECT 'advance.manage' UNION ALL SELECT 'advance.approve'
  UNION ALL SELECT 'expense.view' UNION ALL SELECT 'expense.manage' UNION ALL SELECT 'expense.approve'
  UNION ALL SELECT 'ap.view' UNION ALL SELECT 'ap.manage' UNION ALL SELECT 'ap.approve' UNION ALL SELECT 'ap.pay'
  UNION ALL SELECT 'ar.view' UNION ALL SELECT 'ar.manage' UNION ALL SELECT 'ar.approve'
  UNION ALL SELECT 'budget.view' UNION ALL SELECT 'budget.manage' UNION ALL SELECT 'budget.approve'
  UNION ALL SELECT 'project.view' UNION ALL SELECT 'project.manage'
  UNION ALL SELECT 'asset.view' UNION ALL SELECT 'asset.manage'
  UNION ALL SELECT 'tax.view' UNION ALL SELECT 'tax.manage'
  UNION ALL SELECT 'approval.view' UNION ALL SELECT 'approval.act'
  UNION ALL SELECT 'user.view' UNION ALL SELECT 'user.manage' UNION ALL SELECT 'role.manage'
  UNION ALL SELECT 'settings.manage' UNION ALL SELECT 'audit.view'
) p;

-- Matriks permission role lain
INSERT IGNORE INTO `app_role_permissions` (`role_id`,`permission`)
SELECT r.id, m.permission
FROM `app_roles` r
JOIN (
  SELECT 'dirut' AS role_code, 'dashboard.view' AS permission
  UNION ALL SELECT 'dirut','report.view'      UNION ALL SELECT 'dirut','report.export'
  UNION ALL SELECT 'dirut','journal.view'     UNION ALL SELECT 'dirut','cash.view'
  UNION ALL SELECT 'dirut','cash.approve'
  UNION ALL SELECT 'dirut','advance.view'     UNION ALL SELECT 'dirut','expense.view'
  UNION ALL SELECT 'dirut','ap.view'          UNION ALL SELECT 'dirut','ar.view'
  UNION ALL SELECT 'dirut','budget.view'      UNION ALL SELECT 'dirut','project.view'
  UNION ALL SELECT 'dirut','asset.view'       UNION ALL SELECT 'dirut','tax.view'
  UNION ALL SELECT 'dirut','approval.view'    UNION ALL SELECT 'dirut','approval.act'
  UNION ALL SELECT 'dirut','audit.view'       UNION ALL SELECT 'dirut','master.view'
  UNION ALL SELECT 'dirut','coa.view'         UNION ALL SELECT 'dirut','period.view'
  UNION ALL SELECT 'dirut','user.view'

  UNION ALL SELECT 'dirops','dashboard.view'  UNION ALL SELECT 'dirops','report.view'
  UNION ALL SELECT 'dirops','report.export'   UNION ALL SELECT 'dirops','journal.view'
  UNION ALL SELECT 'dirops','cash.view'       UNION ALL SELECT 'dirops','cash.approve'
  UNION ALL SELECT 'dirops','advance.view'
  UNION ALL SELECT 'dirops','advance.approve'  UNION ALL SELECT 'dirops','expense.view'
  UNION ALL SELECT 'dirops','expense.approve' UNION ALL SELECT 'dirops','ap.view'
  UNION ALL SELECT 'dirops','ap.approve'      UNION ALL SELECT 'dirops','ar.view'
  UNION ALL SELECT 'dirops','budget.view'     UNION ALL SELECT 'dirops','project.view'
  UNION ALL SELECT 'dirops','project.manage'  UNION ALL SELECT 'dirops','asset.view'
  UNION ALL SELECT 'dirops','tax.view'        UNION ALL SELECT 'dirops','approval.view'
  UNION ALL SELECT 'dirops','approval.act'    UNION ALL SELECT 'dirops','master.view'
  UNION ALL SELECT 'dirops','coa.view'        UNION ALL SELECT 'dirops','period.view'

  UNION ALL SELECT 'kabid_keuangan','dashboard.view'  UNION ALL SELECT 'kabid_keuangan','report.view'
  UNION ALL SELECT 'kabid_keuangan','report.export'   UNION ALL SELECT 'kabid_keuangan','coa.view'
  UNION ALL SELECT 'kabid_keuangan','coa.manage'      UNION ALL SELECT 'kabid_keuangan','master.view'
  UNION ALL SELECT 'kabid_keuangan','master.manage'   UNION ALL SELECT 'kabid_keuangan','period.view'
  UNION ALL SELECT 'kabid_keuangan','period.manage'   UNION ALL SELECT 'kabid_keuangan','journal.view'
  UNION ALL SELECT 'kabid_keuangan','journal.create'  UNION ALL SELECT 'kabid_keuangan','journal.submit'
  UNION ALL SELECT 'kabid_keuangan','journal.approve' UNION ALL SELECT 'kabid_keuangan','journal.post'
  UNION ALL SELECT 'kabid_keuangan','journal.reverse' UNION ALL SELECT 'kabid_keuangan','cash.view'
  UNION ALL SELECT 'kabid_keuangan','cash.manage'     UNION ALL SELECT 'kabid_keuangan','cash.approve'
  UNION ALL SELECT 'kabid_keuangan','cash.post'       UNION ALL SELECT 'kabid_keuangan','advance.view'
  UNION ALL SELECT 'kabid_keuangan','advance.manage'  UNION ALL SELECT 'kabid_keuangan','advance.approve'
  UNION ALL SELECT 'kabid_keuangan','expense.view'    UNION ALL SELECT 'kabid_keuangan','expense.manage'
  UNION ALL SELECT 'kabid_keuangan','expense.approve' UNION ALL SELECT 'kabid_keuangan','ap.view'
  UNION ALL SELECT 'kabid_keuangan','ap.manage'       UNION ALL SELECT 'kabid_keuangan','ap.approve'
  UNION ALL SELECT 'kabid_keuangan','ap.pay'          UNION ALL SELECT 'kabid_keuangan','ar.view'
  UNION ALL SELECT 'kabid_keuangan','ar.manage'       UNION ALL SELECT 'kabid_keuangan','ar.approve'
  UNION ALL SELECT 'kabid_keuangan','budget.view'     UNION ALL SELECT 'kabid_keuangan','budget.manage'
  UNION ALL SELECT 'kabid_keuangan','budget.approve'  UNION ALL SELECT 'kabid_keuangan','project.view'
  UNION ALL SELECT 'kabid_keuangan','project.manage'  UNION ALL SELECT 'kabid_keuangan','asset.view'
  UNION ALL SELECT 'kabid_keuangan','asset.manage'    UNION ALL SELECT 'kabid_keuangan','tax.view'
  UNION ALL SELECT 'kabid_keuangan','tax.manage'      UNION ALL SELECT 'kabid_keuangan','approval.view'
  UNION ALL SELECT 'kabid_keuangan','approval.act'    UNION ALL SELECT 'kabid_keuangan','user.view'
  UNION ALL SELECT 'kabid_keuangan','audit.view'

  UNION ALL SELECT 'staff_accounting','dashboard.view' UNION ALL SELECT 'staff_accounting','report.view'
  UNION ALL SELECT 'staff_accounting','report.export'  UNION ALL SELECT 'staff_accounting','coa.view'
  UNION ALL SELECT 'staff_accounting','coa.manage'     UNION ALL SELECT 'staff_accounting','master.view'
  UNION ALL SELECT 'staff_accounting','period.view'    UNION ALL SELECT 'staff_accounting','journal.view'
  UNION ALL SELECT 'staff_accounting','journal.create' UNION ALL SELECT 'staff_accounting','journal.submit'
  UNION ALL SELECT 'staff_accounting','journal.post'   UNION ALL SELECT 'staff_accounting','journal.reverse'
  UNION ALL SELECT 'staff_accounting','cash.view'      UNION ALL SELECT 'staff_accounting','cash.post'
  UNION ALL SELECT 'staff_accounting','advance.view'
  UNION ALL SELECT 'staff_accounting','expense.view'   UNION ALL SELECT 'staff_accounting','ap.view'
  UNION ALL SELECT 'staff_accounting','ar.view'        UNION ALL SELECT 'staff_accounting','budget.view'
  UNION ALL SELECT 'staff_accounting','project.view'   UNION ALL SELECT 'staff_accounting','asset.view'
  UNION ALL SELECT 'staff_accounting','asset.manage'   UNION ALL SELECT 'staff_accounting','tax.view'
  UNION ALL SELECT 'staff_accounting','tax.manage'     UNION ALL SELECT 'staff_accounting','audit.view'
  UNION ALL SELECT 'staff_accounting','approval.view'

  UNION ALL SELECT 'staff_finance','dashboard.view'    UNION ALL SELECT 'staff_finance','report.view'
  UNION ALL SELECT 'staff_finance','report.export'     UNION ALL SELECT 'staff_finance','master.view'
  UNION ALL SELECT 'staff_finance','coa.view'          UNION ALL SELECT 'staff_finance','period.view'
  UNION ALL SELECT 'staff_finance','journal.view'      UNION ALL SELECT 'staff_finance','journal.create'
  UNION ALL SELECT 'staff_finance','journal.submit'    UNION ALL SELECT 'staff_finance','cash.view'
  UNION ALL SELECT 'staff_finance','cash.manage'       UNION ALL SELECT 'staff_finance','cash.post'
  UNION ALL SELECT 'staff_finance','advance.view'
  UNION ALL SELECT 'staff_finance','advance.manage'    UNION ALL SELECT 'staff_finance','advance.approve'
  UNION ALL SELECT 'staff_finance','expense.view'      UNION ALL SELECT 'staff_finance','expense.manage'
  UNION ALL SELECT 'staff_finance','ap.view'           UNION ALL SELECT 'staff_finance','ap.manage'
  UNION ALL SELECT 'staff_finance','ap.pay'            UNION ALL SELECT 'staff_finance','ar.view'
  UNION ALL SELECT 'staff_finance','ar.manage'         UNION ALL SELECT 'staff_finance','budget.view'
  UNION ALL SELECT 'staff_finance','project.view'      UNION ALL SELECT 'staff_finance','asset.view'
  UNION ALL SELECT 'staff_finance','tax.view'          UNION ALL SELECT 'staff_finance','approval.view'

  UNION ALL SELECT 'kepala_divisi','dashboard.view'    UNION ALL SELECT 'kepala_divisi','report.view'
  UNION ALL SELECT 'kepala_divisi','budget.view'       UNION ALL SELECT 'kepala_divisi','budget.manage'
  UNION ALL SELECT 'kepala_divisi','project.view'      UNION ALL SELECT 'kepala_divisi','journal.view'
  UNION ALL SELECT 'kepala_divisi','expense.view'      UNION ALL SELECT 'kepala_divisi','expense.manage'
  UNION ALL SELECT 'kepala_divisi','advance.view'      UNION ALL SELECT 'kepala_divisi','advance.manage'
  UNION ALL SELECT 'kepala_divisi','master.view'       UNION ALL SELECT 'kepala_divisi','approval.view'

  UNION ALL SELECT 'kepala_bidang','dashboard.view'     UNION ALL SELECT 'kepala_bidang','report.view'
  UNION ALL SELECT 'kepala_bidang','budget.view'        UNION ALL SELECT 'kepala_bidang','budget.manage'
  UNION ALL SELECT 'kepala_bidang','budget.approve'     UNION ALL SELECT 'kepala_bidang','project.view'
  UNION ALL SELECT 'kepala_bidang','journal.view'       UNION ALL SELECT 'kepala_bidang','expense.view'
  UNION ALL SELECT 'kepala_bidang','expense.manage'     UNION ALL SELECT 'kepala_bidang','advance.view'
  UNION ALL SELECT 'kepala_bidang','advance.manage'     UNION ALL SELECT 'kepala_bidang','master.view'
  UNION ALL SELECT 'kepala_bidang','approval.view'      UNION ALL SELECT 'kepala_bidang','approval.act'

  UNION ALL SELECT 'auditor','dashboard.view'    UNION ALL SELECT 'auditor','report.view'
  UNION ALL SELECT 'auditor','report.export'     UNION ALL SELECT 'auditor','journal.view'
  UNION ALL SELECT 'auditor','cash.view'         UNION ALL SELECT 'auditor','advance.view'
  UNION ALL SELECT 'auditor','expense.view'      UNION ALL SELECT 'auditor','ap.view'
  UNION ALL SELECT 'auditor','ar.view'           UNION ALL SELECT 'auditor','budget.view'
  UNION ALL SELECT 'auditor','project.view'      UNION ALL SELECT 'auditor','asset.view'
  UNION ALL SELECT 'auditor','tax.view'          UNION ALL SELECT 'auditor','audit.view'
  UNION ALL SELECT 'auditor','master.view'       UNION ALL SELECT 'auditor','coa.view'
  UNION ALL SELECT 'auditor','period.view'       UNION ALL SELECT 'auditor','user.view'
  UNION ALL SELECT 'auditor','approval.view'
) m ON m.role_code = r.code;

-- ---------------------------------------------------------------------
-- 3. MASTER UNIT USAHA, BIDANG, DIVISI, COST CENTER
-- ---------------------------------------------------------------------
INSERT INTO `ms_business_units` (`id`,`kode`,`nama`,`keterangan`) VALUES
(1,'BU-KON','Jasa Konstruksi','Pekerjaan sipil, gedung, dan infrastruktur'),
(2,'BU-ANG','Pengangkutan & Pergudangan','Logistik, angkutan, dan pergudangan'),
(3,'BU-DAG','Perdagangan','Perdagangan umum dan distribusi'),
(4,'BU-MIG','Energi & Migas','Energi terbarukan, migas, dan jasa penunjang')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

INSERT INTO `ms_bidang` (`id`,`kode`,`nama`,`nama_sso`,`business_unit_id`) VALUES
(1,'BID-KON','Bidang Jasa Konstruksi','Jasa Konstruksi',1),
(2,'BID-ANG','Bidang Pengangkutan & Pergudangan','Pengangkutan & Pergudangan',2),
(3,'BID-DAG','Bidang Perdagangan & Energi','Perdagangan',3),
(4,'BID-KEU','Bidang Keuangan & Umum','Keuangan',NULL),
(5,'BID-INF','Bidang Infokom & Digital','Infokom',NULL),
(6,'BID-SDM','Bidang SDM, Legal & Kesekretariatan','SDM',NULL)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `nama_sso` = VALUES(`nama_sso`);

INSERT INTO `ms_divisi` (`id`,`bidang_id`,`kode`,`nama`,`nama_sso`) VALUES
(1,4,'DIV-101','Umum, Legal & SDM','Keuangan & Umum'),
(2,2,'DIV-102','Operasional & Pemeliharaan','Bisnis'),
(3,4,'DIV-103','Keuangan & Akuntansi','Keuangan & Umum'),
(4,1,'DIV-104','Konstruksi & Proyek Sipil','Bisnis'),
(5,3,'DIV-105','Komersial & Pengembangan Bisnis','Bisnis'),
(6,6,'DIV-106','Sumber Daya Manusia','Keuangan & Umum'),
(7,6,'DIV-107','Sekretariat & Hukum','Sekretariat'),
(8,5,'DIV-108','Teknologi Informasi & Digital','Infokom')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `bidang_id` = VALUES(`bidang_id`);

INSERT INTO `ms_cost_centers` (`id`,`kode`,`nama`,`bidang_id`,`divisi_id`,`business_unit_id`,`tipe`) VALUES
(1,'CC-101','Umum, Legal & SDM',4,1,NULL,'cost'),
(2,'CC-102','Operasional & Pemeliharaan',2,2,2,'cost'),
(3,'CC-103','Keuangan & Akuntansi',4,3,NULL,'cost'),
(4,'CC-104','Konstruksi & Proyek Sipil',1,4,1,'profit'),
(5,'CC-105','Komersial & Pengembangan Bisnis',3,5,3,'profit'),
(6,'CC-106','Sumber Daya Manusia',6,6,NULL,'cost'),
(7,'CC-107','Sekretariat & Hukum',6,7,NULL,'cost'),
(8,'CC-108','Teknologi Informasi & Digital',5,8,NULL,'cost'),
(9,'CC-109','Pengadaan & Logistik',4,1,NULL,'cost'),
(10,'CC-110','Direksi',4,NULL,NULL,'cost')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- ---------------------------------------------------------------------
-- 4. VENDOR & CUSTOMER
-- ---------------------------------------------------------------------
INSERT INTO `ms_partners` (`id`,`kode`,`nama`,`tipe`,`npwp`,`kota`,`telepon`,`email`,`contact_person`,`termin_hari`) VALUES
(1,'CUS-001','PT Hutama Karya (Persero)','customer','01.001.002.3-011.000','Jakarta','021-5550101','procurement@hutamakarya.co.id','Bagian Pengadaan',45),
(2,'CUS-002','PT Wijaya Karya (Persero) Tbk','customer','01.002.003.4-012.000','Jakarta','021-5550202','vendor@wika.co.id','Divisi Pengadaan',45),
(3,'CUS-003','PT PLN (Persero) UIP Lampung','customer','01.003.004.5-013.000','Bandar Lampung','0721-5550303','uip.lampung@pln.co.id','Manajer Pengadaan',30),
(4,'CUS-004','Pemerintah Provinsi Lampung','customer','00.000.000.0-000.000','Bandar Lampung','0721-5550404','biro.umum@lampungprov.go.id','Biro Umum',30),
(5,'CUS-005','PT Bukit Asam Tbk','customer','01.005.006.7-015.000','Tanjung Enim','0734-5550505','vendor@bukitasam.co.id','Procurement',60),
(6,'VEN-001','PT Wijaya Perkasa Teknik','vendor','02.101.102.3-321.000','Bandar Lampung','0721-6660101','finance@wpt.co.id','Rina Anggraini',30),
(7,'VEN-002','PT Sinar Jaya Material','vendor','02.102.103.4-322.000','Lampung Selatan','0721-6660202','sales@sinarjaya.co.id','Hendra Wijaya',30),
(8,'VEN-003','CV Mitra Abadi','vendor','02.103.104.5-323.000','Metro','0725-6660303','admin@mitraabadi.co.id','Siti Aminah',14),
(9,'VEN-004','PT Trans Logistik Nusantara','vendor','02.104.105.6-324.000','Bandar Lampung','0721-6660404','billing@tln.co.id','Agus Salim',30),
(10,'VEN-005','PT Bumi Energi Prima','vendor','02.105.106.7-325.000','Palembang','0711-6660505','finance@bumienergi.co.id','Dewi Kartika',45),
(11,'VEN-006','PT Asuransi Jasa Raharja Putera','vendor','02.106.107.8-326.000','Jakarta','021-6660606','corporate@jrputera.co.id','Budi Santoso',30),
(12,'MIX-001','PT Tri Daya Sakti','both','02.107.108.9-327.000','Bandar Lampung','0721-6660707','keuangan@tridayasakti.co.id','Maya Sari',30)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- ---------------------------------------------------------------------
-- 5. REKENING BANK & KAS
-- ---------------------------------------------------------------------
INSERT INTO `ms_bank_accounts` (`id`,`kode`,`nama`,`tipe`,`nama_bank`,`nomor_rekening`,`atas_nama`,`coa_account_id`,`saldo_awal`,`saldo_berjalan`,`saldo_minimum`) VALUES
(1,'BNK-MDR','Mandiri Giro Operasional','bank','Bank Mandiri','070-00-9821445-1','PT Lampung Jasa Utama',7,5000000000,22450000000,500000000),
(2,'BNK-BCA','BCA Payroll & Vendor Pool','bank','Bank BCA','206-389100-2','PT Lampung Jasa Utama',8,3500000000,14820550000,500000000),
(3,'BNK-BNI','BNI Escrow Proyek Strategis','escrow','Bank BNI','011-8932010-9','PT Lampung Jasa Utama',9,2000000000,9650000000,0),
(4,'KAS-PC','Kas Kecil Kantor Pusat (Petty Cash)','petty_cash','Kas','-','Kantor Pusat',4,250000000,1800000000,50000000)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `saldo_berjalan` = VALUES(`saldo_berjalan`);

-- ---------------------------------------------------------------------
-- 6. PROYEK & KONTRAK
-- ---------------------------------------------------------------------
INSERT INTO `ms_projects` (`id`,`kode`,`nama`,`partner_id`,`business_unit_id`,`bidang_id`,`divisi_id`,`cost_center_id`,`nilai_kontrak`,`uang_muka_persen`,`tanggal_mulai`,`tanggal_selesai`,`status`) VALUES
(1,'PRJ-2025-001','Pembangunan Gedung Kantor Regional Lampung',1,1,1,4,4,48000000000,20,DATE_SUB(CURDATE(), INTERVAL 8 MONTH),DATE_ADD(CURDATE(), INTERVAL 10 MONTH),'berjalan'),
(2,'PRJ-2025-002','Rehabilitasi Jaringan Irigasi Way Sekampung',4,1,1,4,4,27500000000,15,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),DATE_ADD(CURDATE(), INTERVAL 7 MONTH),'berjalan'),
(3,'PRJ-2025-003','Pengangkutan Batubara Sumsel - Lampung',5,2,2,2,2,36000000000,10,DATE_SUB(CURDATE(), INTERVAL 11 MONTH),DATE_ADD(CURDATE(), INTERVAL 4 MONTH),'berjalan'),
(4,'PRJ-2025-004','Penyediaan Material Ketenagalistrikan',3,3,3,5,5,18500000000,10,DATE_SUB(CURDATE(), INTERVAL 3 MONTH),DATE_ADD(CURDATE(), INTERVAL 9 MONTH),'berjalan'),
(5,'PRJ-2024-011','Pekerjaan Sipil Gardu Induk Bandar Lampung',2,1,1,4,4,15200000000,20,DATE_SUB(CURDATE(), INTERVAL 20 MONTH),DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'selesai')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `nilai_kontrak` = VALUES(`nilai_kontrak`);

INSERT INTO `ms_contracts` (`id`,`nomor`,`judul`,`partner_id`,`project_id`,`nilai_kontrak`,`tanggal`,`tanggal_mulai`,`tanggal_akhir`,`status`) VALUES
(1,'KTR/2025/001','Kontrak Pembangunan Gedung Kantor Regional Lampung',1,1,48000000000,DATE_SUB(CURDATE(), INTERVAL 8 MONTH),DATE_SUB(CURDATE(), INTERVAL 8 MONTH),DATE_ADD(CURDATE(), INTERVAL 10 MONTH),'aktif'),
(2,'KTR/2025/002','Kontrak Rehabilitasi Jaringan Irigasi Way Sekampung',4,2,27500000000,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),DATE_SUB(CURDATE(), INTERVAL 5 MONTH),DATE_ADD(CURDATE(), INTERVAL 7 MONTH),'aktif'),
(3,'KTR/2025/003','Kontrak Pengangkutan Batubara',5,3,36000000000,DATE_SUB(CURDATE(), INTERVAL 11 MONTH),DATE_SUB(CURDATE(), INTERVAL 11 MONTH),DATE_ADD(CURDATE(), INTERVAL 4 MONTH),'aktif')
ON DUPLICATE KEY UPDATE `judul` = VALUES(`judul`);

INSERT INTO `ms_contract_terms` (`id`,`contract_id`,`termin_ke`,`deskripsi`,`persentase`,`nilai`,`status`) VALUES
(1,1,1,'Uang muka 20%',20,9600000000,'dibayar'),
(2,1,2,'Termin I - progres 35%',35,16800000000,'ditagih'),
(3,1,3,'Termin II - progres 30%',30,14400000000,'belum'),
(4,1,4,'Pelunasan 15%',15,7200000000,'belum'),
(5,2,1,'Uang muka 15%',15,4125000000,'dibayar'),
(6,2,2,'Termin I - progres 40%',40,11000000000,'ditagih'),
(7,2,3,'Pelunasan 45%',45,12375000000,'belum'),
(8,3,1,'Termin bulanan 1',25,9000000000,'dibayar'),
(9,3,2,'Termin bulanan 2',25,9000000000,'dibayar'),
(10,3,3,'Termin bulanan 3',25,9000000000,'ditagih'),
(11,3,4,'Termin bulanan 4',25,9000000000,'belum')
ON DUPLICATE KEY UPDATE `deskripsi` = VALUES(`deskripsi`);

-- ---------------------------------------------------------------------
-- 7. PERIODE AKUNTANSI (tahun lalu, tahun ini, tahun depan) — dinamis
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `fiscal_years` (`tahun`,`tanggal_mulai`,`tanggal_selesai`,`status`,`catatan`)
SELECT y.tahun, DATE(CONCAT(y.tahun,'-01-01')), DATE(CONCAT(y.tahun,'-12-31')),
       CASE WHEN y.tahun < YEAR(CURDATE()) THEN 'closed' ELSE 'open' END,
       IF(y.tahun < YEAR(CURDATE()), 'Tutup buku tahunan', 'Tahun buku berjalan')
FROM (SELECT YEAR(CURDATE()) - 1 AS tahun UNION SELECT YEAR(CURDATE()) UNION SELECT YEAR(CURDATE()) + 1) y;

INSERT IGNORE INTO `fiscal_periods` (`tahun`,`bulan`,`kode`,`nama`,`tanggal_mulai`,`tanggal_selesai`,`status`)
SELECT y.tahun, m.bulan,
       CONCAT(y.tahun,'-',LPAD(m.bulan,2,'0')),
       CONCAT(ELT(m.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),' ',y.tahun),
       DATE(CONCAT(y.tahun,'-',LPAD(m.bulan,2,'0'),'-01')),
       LAST_DAY(DATE(CONCAT(y.tahun,'-',LPAD(m.bulan,2,'0'),'-01'))),
       CASE
         WHEN y.tahun < YEAR(CURDATE()) THEN 'closed'
         WHEN y.tahun = YEAR(CURDATE()) AND m.bulan < MONTH(CURDATE()) THEN 'closed'
         ELSE 'open'
       END
FROM (SELECT YEAR(CURDATE()) - 1 AS tahun UNION SELECT YEAR(CURDATE()) UNION SELECT YEAR(CURDATE()) + 1) y
CROSS JOIN (SELECT 1 AS bulan UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
            UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) m;

-- ---------------------------------------------------------------------
-- 8. CHART OF ACCOUNTS (id eksplisit agar parent_id mudah dirawat)
-- ---------------------------------------------------------------------
INSERT INTO `coa_accounts` (`id`,`kode`,`nama`,`tipe`,`kategori`,`parent_id`,`level`,`saldo_normal`,`allow_posting`,`is_kas_bank`,`is_terkontrol`,`arus_kas`,`is_active`) VALUES
-- ASET
(1,'1-0000','Aset','aset','aset_lancar',NULL,1,'D',0,0,0,'operasi',1),
(2,'1-1000','Aset Lancar','aset','aset_lancar',1,2,'D',0,0,0,'operasi',1),
(3,'1-1100','Kas','aset','aset_lancar',2,3,'D',0,1,0,'operasi',1),
(4,'1-1110','Kas Kecil Kantor Pusat','aset','aset_lancar',3,4,'D',1,1,1,'operasi',1),
(5,'1-1120','Kas Kecil Proyek','aset','aset_lancar',3,4,'D',1,1,1,'operasi',1),
(6,'1-1200','Bank','aset','aset_lancar',2,3,'D',0,1,0,'operasi',1),
(7,'1-1210','Bank Mandiri Giro Operasional','aset','aset_lancar',6,4,'D',1,1,1,'operasi',1),
(8,'1-1220','Bank BCA Payroll & Vendor Pool','aset','aset_lancar',6,4,'D',1,1,1,'operasi',1),
(9,'1-1230','Bank BNI Escrow Proyek Strategis','aset','aset_lancar',6,4,'D',1,1,1,'operasi',1),
(10,'1-1240','Bank Mandiri Payroll','aset','aset_lancar',6,4,'D',1,1,1,'operasi',1),
(11,'1-1300','Piutang Usaha','aset','aset_lancar',2,3,'D',0,0,0,'operasi',1),
(12,'1-1310','Piutang Usaha - Pihak Ketiga','aset','aset_lancar',11,4,'D',1,0,1,'operasi',1),
(13,'1-1320','Piutang Retensi','aset','aset_lancar',11,4,'D',1,0,1,'operasi',1),
(14,'1-1400','Uang Muka','aset','aset_lancar',2,3,'D',0,0,0,'operasi',1),
(15,'1-1410','Uang Muka Kegiatan','aset','aset_lancar',14,4,'D',1,0,1,'operasi',1),
(16,'1-1420','Uang Muka Pembelian','aset','aset_lancar',14,4,'D',1,0,1,'operasi',1),
(17,'1-1430','PPN Masukan','aset','aset_lancar',14,4,'D',1,0,0,'operasi',1),
(18,'1-1500','Persediaan','aset','aset_lancar',2,3,'D',0,0,0,'operasi',1),
(19,'1-1510','Persediaan Material','aset','aset_lancar',18,4,'D',1,0,0,'operasi',1),
(20,'1-1520','Persediaan Barang Dagang','aset','aset_lancar',18,4,'D',1,0,0,'operasi',1),
(21,'1-1600','Biaya Dibayar Dimuka','aset','aset_lancar',2,3,'D',0,0,0,'operasi',1),
(22,'1-1610','Sewa Dibayar Dimuka','aset','aset_lancar',21,4,'D',1,0,0,'operasi',1),
(23,'1-1620','Asuransi Dibayar Dimuka','aset','aset_lancar',21,4,'D',1,0,0,'operasi',1),
(24,'1-1900','Aset Lancar Lain-lain','aset','aset_lancar',2,3,'D',1,0,0,'operasi',1),
(25,'1-2000','Aset Tidak Lancar','aset','aset_tetap',1,2,'D',0,0,0,'investasi',1),
(26,'1-2100','Aset Tetap','aset','aset_tetap',25,3,'D',0,0,0,'investasi',1),
(27,'1-2110','Tanah','aset','aset_tetap',26,4,'D',1,0,0,'investasi',1),
(28,'1-2120','Bangunan & Gedung','aset','aset_tetap',26,4,'D',1,0,0,'investasi',1),
(29,'1-2130','Kendaraan','aset','aset_tetap',26,4,'D',1,0,0,'investasi',1),
(30,'1-2140','Peralatan & Mesin','aset','aset_tetap',26,4,'D',1,0,0,'investasi',1),
(31,'1-2150','Inventaris Kantor','aset','aset_tetap',26,4,'D',1,0,0,'investasi',1),
(32,'1-2190','Akumulasi Penyusutan','aset','aset_tetap',26,3,'K',0,0,0,'non_kas',1),
(33,'1-2191','Akumulasi Penyusutan Bangunan','aset','aset_tetap',32,4,'K',1,0,1,'non_kas',1),
(34,'1-2192','Akumulasi Penyusutan Kendaraan','aset','aset_tetap',32,4,'K',1,0,1,'non_kas',1),
(35,'1-2193','Akumulasi Penyusutan Peralatan','aset','aset_tetap',32,4,'K',1,0,1,'non_kas',1),
(36,'1-2194','Akumulasi Penyusutan Inventaris','aset','aset_tetap',32,4,'K',1,0,1,'non_kas',1),
(37,'1-2200','Aset Tidak Berwujud','aset','aset_tetap',25,3,'D',1,0,0,'investasi',1),
(38,'1-2300','Aset Lain-lain','aset','aset_lain',25,3,'D',1,0,0,'investasi',1),
(39,'1-2900','Aset Tidak Lancar Lainnya','aset','aset_lain',25,3,'D',1,0,0,'investasi',1),
-- KEWAJIBAN
(40,'2-0000','Kewajiban','kewajiban','kewajiban_lancar',NULL,1,'K',0,0,0,'operasi',1),
(41,'2-1000','Kewajiban Jangka Pendek','kewajiban','kewajiban_lancar',40,2,'K',0,0,0,'operasi',1),
(42,'2-1100','Utang Usaha','kewajiban','kewajiban_lancar',41,3,'K',0,0,0,'operasi',1),
(43,'2-1110','Utang Usaha - Vendor','kewajiban','kewajiban_lancar',42,4,'K',1,0,1,'operasi',1),
(44,'2-1120','Utang Retensi','kewajiban','kewajiban_lancar',42,4,'K',1,0,0,'operasi',1),
(45,'2-1200','Utang Pajak','kewajiban','kewajiban_lancar',41,3,'K',0,0,0,'operasi',1),
(46,'2-1210','Utang PPN Keluaran','kewajiban','kewajiban_lancar',45,4,'K',1,0,1,'operasi',1),
(47,'2-1220','Utang PPh 21','kewajiban','kewajiban_lancar',45,4,'K',1,0,1,'operasi',1),
(48,'2-1230','Utang PPh 23','kewajiban','kewajiban_lancar',45,4,'K',1,0,1,'operasi',1),
(49,'2-1240','Utang PPh 4 ayat (2)','kewajiban','kewajiban_lancar',45,4,'K',1,0,1,'operasi',1),
(50,'2-1250','Utang PPh 22','kewajiban','kewajiban_lancar',45,4,'K',1,0,1,'operasi',1),
(51,'2-1300','Utang Gaji & Tunjangan','kewajiban','kewajiban_lancar',41,3,'K',1,0,0,'operasi',1),
(52,'2-1400','Biaya Masih Harus Dibayar','kewajiban','kewajiban_lancar',41,3,'K',1,0,0,'operasi',1),
(53,'2-1500','Uang Muka Pelanggan','kewajiban','kewajiban_lancar',41,3,'K',1,0,0,'operasi',1),
(54,'2-1600','Utang Bank Jangka Pendek','kewajiban','kewajiban_lancar',41,3,'K',1,0,0,'pendanaan',1),
(55,'2-1900','Kewajiban Lancar Lainnya','kewajiban','kewajiban_lancar',41,3,'K',1,0,0,'operasi',1),
(56,'2-2000','Kewajiban Jangka Panjang','kewajiban','kewajiban_panjang',40,2,'K',0,0,0,'pendanaan',1),
(57,'2-2100','Utang Bank Jangka Panjang','kewajiban','kewajiban_panjang',56,3,'K',1,0,0,'pendanaan',1),
(58,'2-2200','Utang Leasing','kewajiban','kewajiban_panjang',56,3,'K',1,0,0,'pendanaan',1),
(59,'2-2300','Liabilitas Imbalan Kerja','kewajiban','kewajiban_panjang',56,3,'K',1,0,0,'operasi',1),
(60,'2-2900','Kewajiban Jangka Panjang Lainnya','kewajiban','kewajiban_panjang',56,3,'K',1,0,0,'pendanaan',1),
-- EKUITAS
(61,'3-0000','Ekuitas','ekuitas','ekuitas',NULL,1,'K',0,0,0,'pendanaan',1),
(62,'3-1000','Modal Disetor','ekuitas','ekuitas',61,2,'K',1,0,0,'pendanaan',1),
(63,'3-1100','Tambahan Modal Disetor','ekuitas','ekuitas',61,2,'K',1,0,0,'pendanaan',1),
(64,'3-2000','Saldo Laba','ekuitas','ekuitas',61,2,'K',1,0,0,'pendanaan',1),
(65,'3-2100','Laba (Rugi) Tahun Berjalan','ekuitas','ekuitas',61,2,'K',1,0,0,'pendanaan',1),
(66,'3-3000','Dividen','ekuitas','ekuitas',61,2,'D',1,0,0,'pendanaan',1),
-- PENDAPATAN
(67,'4-0000','Pendapatan','pendapatan','pendapatan_usaha',NULL,1,'K',0,0,0,'operasi',1),
(68,'4-1000','Pendapatan Usaha','pendapatan','pendapatan_usaha',67,2,'K',0,0,0,'operasi',1),
(69,'4-1100','Pendapatan Jasa Konstruksi','pendapatan','pendapatan_usaha',68,3,'K',1,0,0,'operasi',1),
(70,'4-1200','Pendapatan Pengangkutan & Pergudangan','pendapatan','pendapatan_usaha',68,3,'K',1,0,0,'operasi',1),
(71,'4-1300','Pendapatan Perdagangan','pendapatan','pendapatan_usaha',68,3,'K',1,0,0,'operasi',1),
(72,'4-1400','Pendapatan Energi & Migas','pendapatan','pendapatan_usaha',68,3,'K',1,0,0,'operasi',1),
(73,'4-1500','Pendapatan Jasa Lainnya','pendapatan','pendapatan_usaha',68,3,'K',1,0,0,'operasi',1),
(74,'4-2000','Pendapatan Lain-lain','pendapatan','pendapatan_lain',67,2,'K',0,0,0,'operasi',1),
(75,'4-2100','Pendapatan Bunga','pendapatan','pendapatan_lain',74,3,'K',1,0,0,'operasi',1),
(76,'4-2200','Laba Penjualan Aset Tetap','pendapatan','pendapatan_lain',74,3,'K',1,0,0,'investasi',1),
(77,'4-2300','Pendapatan Lain-lain','pendapatan','pendapatan_lain',74,3,'K',1,0,0,'operasi',1),
(78,'4-3000','Potongan & Retur Penjualan','pendapatan','pendapatan_usaha',67,2,'D',1,0,0,'operasi',1),
-- BEBAN
(79,'5-0000','Beban','beban','beban_operasional',NULL,1,'D',0,0,0,'operasi',1),
(80,'5-1000','Beban Pokok Pendapatan','beban','harga_pokok',79,2,'D',0,0,0,'operasi',1),
(81,'5-1100','Beban Material Proyek','beban','harga_pokok',80,3,'D',1,0,0,'operasi',1),
(82,'5-1200','Beban Upah & Tenaga Kerja Langsung','beban','harga_pokok',80,3,'D',1,0,0,'operasi',1),
(83,'5-1300','Beban Subkontraktor','beban','harga_pokok',80,3,'D',1,0,0,'operasi',1),
(84,'5-1400','Beban Sewa Alat Berat','beban','harga_pokok',80,3,'D',1,0,0,'operasi',1),
(85,'5-1500','Beban BBM & Transportasi Proyek','beban','harga_pokok',80,3,'D',1,0,0,'operasi',1),
(86,'5-1600','Beban Pokok Perdagangan','beban','harga_pokok',80,3,'D',1,0,0,'operasi',1),
(87,'5-2000','Beban Operasional','beban','beban_operasional',79,2,'D',0,0,0,'operasi',1),
(88,'5-2100','Beban Gaji & Tunjangan','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(89,'5-2120','Beban BPJS & Jaminan Sosial','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(90,'5-2130','Beban Perjalanan Dinas','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(91,'5-2200','Beban Sewa Kantor','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(92,'5-2210','Beban Listrik, Air & Telekomunikasi','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(93,'5-2220','Beban Pemeliharaan & Perbaikan','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(94,'5-2230','Beban Perlengkapan Kantor','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(95,'5-2240','Beban Konsumsi & Rapat','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(96,'5-2250','Beban Jasa Profesional','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(97,'5-2260','Beban Asuransi','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(98,'5-2270','Beban Penyusutan Aset Tetap','beban','beban_operasional',87,3,'D',1,0,0,'non_kas',1),
(99,'5-2280','Beban Amortisasi','beban','beban_operasional',87,3,'D',1,0,0,'non_kas',1),
(100,'5-2300','Beban Pemasaran & Promosi','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(101,'5-2400','Beban Transportasi & BBM Operasional','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(102,'5-2500','Beban Perizinan & Retribusi','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(103,'5-2600','Beban Pelatihan & Pengembangan SDM','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(104,'5-2900','Beban Operasional Lainnya','beban','beban_operasional',87,3,'D',1,0,0,'operasi',1),
(105,'5-3000','Beban Lain-lain','beban','beban_lain',79,2,'D',0,0,0,'operasi',1),
(106,'5-3100','Beban Administrasi Bank','beban','beban_lain',105,3,'D',1,0,0,'operasi',1),
(107,'5-3200','Beban Bunga & Denda Pajak','beban','beban_lain',105,3,'D',1,0,0,'operasi',1),
(108,'5-3300','Beban Pajak Final','beban','pajak',105,3,'D',1,0,0,'operasi',1),
(109,'5-3900','Beban Lain-lain Lainnya','beban','beban_lain',105,3,'D',1,0,0,'operasi',1),
(110,'1-1440','PPh Dibayar Dimuka','aset','aset_lancar',14,4,'D',1,0,0,'operasi',1),
(111,'1-1330','Piutang Belum Ditagih','aset','aset_lancar',11,4,'D',1,0,1,'operasi',1)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `parent_id` = VALUES(`parent_id`), `kategori` = VALUES(`kategori`);

-- ---------------------------------------------------------------------
-- 9. TARIF & JENIS PAJAK
-- ---------------------------------------------------------------------
INSERT INTO `tax_types` (`id`,`kode`,`nama`,`jenis`,`tarif`,`coa_keluaran_id`,`coa_masukan_id`,`is_potongan`) VALUES
(1,'PPN','Pajak Pertambahan Nilai 11%','ppn',11.00,46,17,0),
(2,'PPH21','PPh Pasal 21 (Pegawai)','pph21',5.00,47,NULL,1),
(3,'PPH22','PPh Pasal 22 (Pengadaan)','pph22',1.50,50,NULL,1),
(4,'PPH23','PPh Pasal 23 (Jasa)','pph23',2.00,48,NULL,1),
(5,'PPH4-2','PPh Pasal 4 ayat (2) - Sewa/Jasa Konstruksi','pph4_2',10.00,49,NULL,1),
(6,'PPH-FINAL','PPh Final UMKM','pph_final',0.50,NULL,NULL,1)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `tarif` = VALUES(`tarif`);

-- ---------------------------------------------------------------------
-- 10. KATEGORI ASET TETAP
-- ---------------------------------------------------------------------
INSERT INTO `asset_categories` (`id`,`kode`,`nama`,`metode`,`umur_bulan`,`tarif_tahunan`,`coa_aset_id`,`coa_akumulasi_id`,`coa_beban_penyusutan_id`) VALUES
(1,'AST-BGN','Bangunan & Gedung','garis_lurus',240,5.00,28,33,98),
(2,'AST-KDR','Kendaraan','garis_lurus',60,20.00,29,34,98),
(3,'AST-PLT','Peralatan & Mesin','garis_lurus',96,12.50,30,35,98),
(4,'AST-INV','Inventaris Kantor','garis_lurus',48,25.00,31,36,98),
(5,'AST-TNH','Tanah','garis_lurus',0,0.00,27,NULL,NULL)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- ---------------------------------------------------------------------
-- 11. KONFIGURASI APPROVAL
-- ---------------------------------------------------------------------
INSERT INTO `approval_flows` (`id`,`kode`,`nama`,`module`,`min_amount`,`max_amount`,`level`,`approver_role_id`,`sla_jam`) VALUES
(1,'AF-JU-1','Jurnal Umum <= 10 juta','journal',0,10000000,1,4,24),
(2,'AF-JU-2','Jurnal Umum > 10 juta','journal',10000000,0,1,3,24),
(3,'AF-JU-3','Jurnal Umum > 10 juta (final)','journal',10000000,0,2,2,48),
(4,'AF-CA-1','Uang Muka <= 25 juta','cash_advance',0,25000000,1,4,24),
(5,'AF-CA-2','Uang Muka > 25 juta','cash_advance',25000000,0,1,4,24),
(6,'AF-CA-3','Uang Muka > 25 juta (final)','cash_advance',25000000,0,2,3,48),
(7,'AF-EXP-1','Biaya <= 10 juta','expense',0,10000000,1,4,24),
(8,'AF-EXP-2','Biaya > 10 juta','expense',10000000,0,1,4,24),
(9,'AF-EXP-3','Biaya > 10 juta (final)','expense',10000000,0,2,3,48),
(10,'AF-AP-1','Tagihan Vendor <= 50 juta','ap_invoice',0,50000000,1,4,24),
(11,'AF-AP-2','Tagihan Vendor > 50 juta','ap_invoice',50000000,0,1,4,24),
(12,'AF-AP-3','Tagihan Vendor > 50 juta (final)','ap_invoice',50000000,0,2,2,48),
(13,'AF-APP-1','Pembayaran Hutang','ap_payment',0,0,1,4,24),
(14,'AF-AR-1','Invoice Piutang','ar_invoice',0,0,1,4,24),
(15,'AF-BUD-1','RKAP Tahunan','budget',0,0,1,4,72),
(16,'AF-BUD-2','RKAP Tahunan (final)','budget',0,0,2,2,72),
(17,'AF-BUD-3','Revisi RKAP','budget',0,0,1,8,72),
(18,'AF-AST-1','Penghapusan Aset','asset_disposal',0,0,1,4,48)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- ---------------------------------------------------------------------
-- 12. PENOMORAN DOKUMEN
-- ---------------------------------------------------------------------
INSERT INTO `doc_sequences` (`code`,`period_key`,`prefix`,`last_number`,`padding`) VALUES
('JU', DATE_FORMAT(CURDATE(),'%Y'), CONCAT('JU/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'), 0, 4),
('KK', DATE_FORMAT(CURDATE(),'%Y'), CONCAT('KK/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'), 0, 4),
('KM', DATE_FORMAT(CURDATE(),'%Y'), CONCAT('KM/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'), 0, 4),
('TRF',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('TRF/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'),0, 4),
('CA', DATE_FORMAT(CURDATE(),'%Y'), CONCAT('CA/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'), 0, 4),
('EXP',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('EXP/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'),0, 4),
('AP', DATE_FORMAT(CURDATE(),'%Y'), CONCAT('AP/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'), 0, 4),
('APP',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('APP/',DATE_FORMAT(CURDATE(),'%Y'),'/'),0, 4),
('AR', DATE_FORMAT(CURDATE(),'%Y'), CONCAT('AR/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'), 0, 4),
('ARP',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('ARP/',DATE_FORMAT(CURDATE(),'%Y'),'/'),0, 4),
('REV',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('REV/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'),0, 4),
('AST',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('AST/',DATE_FORMAT(CURDATE(),'%Y'),'/'),0, 4),
('DEP',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('DEP/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'),0, 4),
('TAX',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('TAX/',DATE_FORMAT(CURDATE(),'%m/%Y'),'/'),0, 4),
('RKAP',DATE_FORMAT(CURDATE(),'%Y'), CONCAT('RKAP/',DATE_FORMAT(CURDATE(),'%Y'),'/'),0, 2)
ON DUPLICATE KEY UPDATE `prefix` = VALUES(`prefix`);

SET FOREIGN_KEY_CHECKS = 1;
