-- =====================================================================
-- LJU Finance & Accounting System
-- 02_demo.sql — data contoh (OPSIONAL) agar dashboard & laporan langsung
-- berisi. Tanggal dibuat relatif terhadap CURDATE() sehingga selalu segar.
-- JANGAN diimport di database produksi.
-- =====================================================================
SET NAMES utf8mb4;
SET @today   := CURDATE();
SET @tahun   := YEAR(CURDATE());
SET @periode := (SELECT `id` FROM `fiscal_periods` WHERE `kode` = DATE_FORMAT(CURDATE(),'%Y-%m'));

-- ---------------------------------------------------------------------
-- 1. PENGGUNA (proyeksi SSO) & ROLE
-- ---------------------------------------------------------------------
INSERT INTO `app_users` (`id`,`nik`,`name`,`username`,`email`,`position`,`nama_bidang`,`nama_divisi`,`sso_role`,`avatar`,`bidang_id`,`divisi_id`,`is_active`) VALUES
(1,'LJU-001','Bambang Hartono','bambang.hartono','bambang@lju.co.id','Direktur Utama','Direksi','Direksi','admin',NULL,4,NULL,1),
(2,'LJU-002','Siti Rahmayanti','siti.rahmayanti','siti@lju.co.id','Kepala Bidang','Keuangan','Keuangan & Umum','user',NULL,4,3,1),
(3,'LJU-003','Hendri Santoso','hendri.santoso','hendri@lju.co.id','Kepala Divisi','Infokom','Infokom','user',NULL,5,8,1),
(4,'LJU-004','Budi Darmawan','budi.darmawan','budi@lju.co.id','Staff','Keuangan','Keuangan & Umum','user',NULL,4,3,1),
(5,'LJU-005','Rina Kartika','rina.kartika','rina@lju.co.id','Staff','Keuangan','Keuangan & Umum','user',NULL,4,3,1),
(6,'LJU-006','Agus Setiawan','agus.setiawan','agus@lju.co.id','Kepala Bidang','Jasa Konstruksi','Bisnis','user',NULL,1,4,1),
(7,'LJU-007','Dewi Lestari','dewi.lestari','dewi@lju.co.id','Manager','Direksi','Pengawas Internal','user',NULL,4,NULL,1),
(8,'LJU-008','Administrator Sistem','administrator','admin@lju.co.id','System Administrator','Infokom','Infokom','admin',NULL,5,8,1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `position` = VALUES(`position`);

INSERT IGNORE INTO `app_user_roles` (`user_id`,`role_id`) VALUES
(1,2),(2,4),(3,7),(4,6),(5,5),(6,8),(7,9),(8,1);

-- ---------------------------------------------------------------------
-- 2. JURNAL (posted, seimbang) — sumber angka dashboard
-- ---------------------------------------------------------------------
INSERT INTO `journal_entries` (`nomor`,`tanggal`,`periode_id`,`fiscal_year`,`tipe`,`sumber`,`referensi`,`deskripsi`,`total_debit`,`total_kredit`,`status`,`created_by`,`posted_by`,`posted_at`) VALUES
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),@today,@periode,@tahun,'umum','manual',NULL,'Pengakuan pendapatan jasa konstruksi',120000000000.00,120000000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),@today,@periode,@tahun,'umum','manual',NULL,'Pengakuan pendapatan pengangkutan & pergudangan',55000000000.00,55000000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0003'),@today,@periode,@tahun,'umum','manual',NULL,'Pengakuan pendapatan perdagangan',35000000000.00,35000000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0004'),@today,@periode,@tahun,'umum','manual',NULL,'Realisasi biaya konstruksi & proyek sipil',68400000000.00,68400000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0005'),@today,@periode,@tahun,'umum','manual',NULL,'Realisasi biaya operasional & pemeliharaan',41200000000.00,41200000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0006'),@today,@periode,@tahun,'umum','manual',NULL,'Realisasi biaya teknologi informasi & digital',14800000000.00,14800000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0007'),@today,@periode,@tahun,'umum','manual',NULL,'Realisasi biaya komersial & pengembangan bisnis',11250000000.00,11250000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0008'),@today,@periode,@tahun,'umum','manual',NULL,'Realisasi biaya umum, legal & SDM',7200000000.00,7200000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0009'),@today,@periode,@tahun,'penyesuaian','penyesuaian',NULL,'Penyusutan aset tetap bulan berjalan',1250000000.00,1250000000.00,'posted',5,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0010'),@today,@periode,@tahun,'pengeluaran','ap',NULL,'Pembayaran tagihan vendor proyek',5000000000.00,5000000000.00,'posted',4,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0011'),@today,@periode,@tahun,'penerimaan','ar',NULL,'Penerimaan pembayaran piutang pelanggan',7500000000.00,7500000000.00,'posted',4,4,NOW()),
(CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0012'),@today,@periode,@tahun,'pengeluaran','uang_muka',NULL,'Pencairan uang muka kegiatan operasional',145000000.00,145000000.00,'posted',4,4,NOW());

SET @j1 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0001'));
SET @j2 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0002'));
SET @j3 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0003'));
SET @j4 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0004'));
SET @j5 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0005'));
SET @j6 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0006'));
SET @j7 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0007'));
SET @j8 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0008'));
SET @j9 := (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0009'));
SET @j10:= (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0010'));
SET @j11:= (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0011'));
SET @j12:= (SELECT id FROM journal_entries WHERE nomor = CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0012'));

INSERT INTO `journal_lines` (`journal_id`,`urutan`,`coa_id`,`deskripsi`,`debit`,`kredit`,`bidang_id`,`divisi_id`,`business_unit_id`,`project_id`,`cost_center_id`) VALUES
(@j1,1,12,'Piutang termin proyek gedung regional',120000000000.00,0,1,4,1,1,4),
(@j1,2,69,'Pendapatan jasa konstruksi',0,120000000000.00,1,4,1,1,4),
(@j2,1,12,'Piutang jasa pengangkutan batubara',55000000000.00,0,2,2,2,3,2),
(@j2,2,70,'Pendapatan pengangkutan & pergudangan',0,55000000000.00,2,2,2,3,2),
(@j3,1,12,'Piutang penjualan material',35000000000.00,0,3,5,3,4,5),
(@j3,2,71,'Pendapatan perdagangan',0,35000000000.00,3,5,3,4,5),
(@j4,1,81,'Material proyek',40000000000.00,0,1,4,1,1,4),
(@j4,2,82,'Upah & tenaga kerja langsung',18400000000.00,0,1,4,1,1,4),
(@j4,3,83,'Subkontraktor',10000000000.00,0,1,4,1,1,4),
(@j4,4,43,'Utang vendor konstruksi',0,30000000000.00,1,4,1,1,4),
(@j4,5,7,'Pembayaran tunai bank Mandiri',0,38400000000.00,1,4,1,1,4),
(@j5,1,101,'BBM & transportasi operasional',15000000000.00,0,2,2,2,3,2),
(@j5,2,84,'Sewa alat berat',12200000000.00,0,2,2,2,3,2),
(@j5,3,93,'Pemeliharaan & perbaikan',14000000000.00,0,2,2,2,3,2),
(@j5,4,43,'Utang vendor operasional',0,41200000000.00,2,2,2,3,2),
(@j6,1,96,'Jasa profesional teknologi',8000000000.00,0,5,8,NULL,NULL,8),
(@j6,2,91,'Sewa kantor & co-working',4300000000.00,0,5,8,NULL,NULL,8),
(@j6,3,94,'Perlengkapan & perangkat',2500000000.00,0,5,8,NULL,NULL,8),
(@j6,4,8,'Bank BCA',0,14800000000.00,5,8,NULL,NULL,8),
(@j7,1,100,'Pemasaran & promosi',6250000000.00,0,3,5,3,4,5),
(@j7,2,90,'Perjalanan dinas komersial',5000000000.00,0,3,5,3,4,5),
(@j7,3,43,'Utang vendor komersial',0,11250000000.00,3,5,3,4,5),
(@j8,1,88,'Gaji & tunjangan',4700000000.00,0,4,1,NULL,NULL,1),
(@j8,2,92,'Listrik, air & telekomunikasi',1500000000.00,0,4,1,NULL,NULL,1),
(@j8,3,95,'Konsumsi & rapat',1000000000.00,0,4,1,NULL,NULL,1),
(@j8,4,8,'Bank BCA',0,7200000000.00,4,1,NULL,NULL,1),
(@j9,1,98,'Beban penyusutan aset tetap',1250000000.00,0,4,NULL,NULL,NULL,1),
(@j9,2,34,'Akumulasi penyusutan kendaraan',0,450000000.00,4,NULL,NULL,NULL,1),
(@j9,3,35,'Akumulasi penyusutan peralatan',0,350000000.00,4,NULL,NULL,NULL,1),
(@j9,4,36,'Akumulasi penyusutan inventaris',0,450000000.00,4,NULL,NULL,NULL,1),
(@j10,1,43,'Pelunasan utang vendor',5000000000.00,0,1,4,1,1,4),
(@j10,2,7,'Bank Mandiri',0,5000000000.00,1,4,1,1,4),
(@j11,1,7,'Penerimaan piutang via bank Mandiri',7500000000.00,0,1,4,1,1,4),
(@j11,2,12,'Piutang usaha',0,7500000000.00,1,4,1,1,4),
(@j12,1,15,'Uang muka kegiatan operasional',145000000.00,0,2,2,2,3,2),
(@j12,2,7,'Bank Mandiri',0,145000000.00,2,2,2,3,2);

-- ---------------------------------------------------------------------
-- 3. PIUTANG USAHA (aging mengikuti CURDATE)
-- ---------------------------------------------------------------------
INSERT INTO `ar_invoices` (`nomor`,`partner_id`,`project_id`,`contract_id`,`termin_ke`,`tanggal`,`tanggal_jatuh_tempo`,`termin_hari`,`deskripsi`,`bidang_id`,`divisi_id`,`cost_center_id`,`business_unit_id`,`subtotal`,`ppn`,`total`,`dibayar`,`sisa`,`status`,`journal_id`,`created_by`,`approved_by`,`approved_at`) VALUES
(CONCAT('AR/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),3,4,NULL,1,DATE_SUB(@today,INTERVAL 20 DAY),DATE_ADD(@today,INTERVAL 10 DAY),30,'Penyediaan material ketenagalistrikan termin I',3,5,5,3,13513513513.51,1486486486.49,15000000000.00,0,15000000000.00,'posted',@j3,4,2,NOW()),
(CONCAT('AR/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),1,1,1,2,DATE_SUB(@today,INTERVAL 50 DAY),DATE_SUB(@today,INTERVAL 5 DAY),45,'Termin I gedung kantor regional (progres 35%)',1,4,4,1,11009009009.01,1210099009.99,12220000000.00,0,12220000000.00,'overdue',@j1,4,2,NOW()),
(CONCAT('AR/',DATE_FORMAT(@today,'%m/%Y'),'/0003'),5,3,3,3,DATE_SUB(@today,INTERVAL 75 DAY),DATE_SUB(@today,INTERVAL 45 DAY),30,'Termin bulanan 3 pengangkutan batubara',2,2,2,2,2837837837.84,312162162.16,3150000000.00,0,3150000000.00,'overdue',@j2,4,2,NOW()),
(CONCAT('AR/',DATE_FORMAT(@today,'%m/%Y'),'/0004'),4,2,2,2,DATE_SUB(@today,INTERVAL 100 DAY),DATE_SUB(@today,INTERVAL 70 DAY),30,'Termin I rehabilitasi irigasi way sekampung',1,4,4,1,1117117117.12,122882882.88,1240000000.00,0,1240000000.00,'overdue',@j1,4,2,NOW()),
(CONCAT('AR/',DATE_FORMAT(@today,'%m/%Y'),'/0005'),2,5,NULL,4,DATE_SUB(@today,INTERVAL 150 DAY),DATE_SUB(@today,INTERVAL 120 DAY),30,'Pelunasan pekerjaan gardu induk (retensi)',1,4,4,1,720720720.72,79279279.28,800000000.00,0,800000000.00,'overdue',@j1,4,2,NOW())
ON DUPLICATE KEY UPDATE `sisa` = VALUES(`sisa`);

-- ---------------------------------------------------------------------
-- 4. HUTANG USAHA
-- ---------------------------------------------------------------------
INSERT INTO `ap_invoices` (`nomor`,`nomor_supplier`,`partner_id`,`tanggal`,`tanggal_jatuh_tempo`,`termin_hari`,`project_id`,`bidang_id`,`divisi_id`,`cost_center_id`,`subtotal`,`ppn`,`total`,`dibayar`,`sisa`,`status`,`journal_id`,`created_by`,`approved_by`,`approved_at`) VALUES
(CONCAT('AP/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),'WPT/INV/2025/1187',6,DATE_SUB(@today,INTERVAL 25 DAY),DATE_ADD(@today,INTERVAL 5 DAY),30,1,1,4,4,7207207207.21,792792792.79,8000000000.00,0,8000000000.00,'posted',@j4,4,2,NOW()),
(CONCAT('AP/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),'SJM/2025/0912',7,DATE_SUB(@today,INTERVAL 40 DAY),DATE_SUB(@today,INTERVAL 10 DAY),30,1,1,4,4,3963963963.96,436036036.04,4400000000.00,0,4400000000.00,'overdue',@j4,4,2,NOW()),
(CONCAT('AP/',DATE_FORMAT(@today,'%m/%Y'),'/0003'),'TLN/2025/0771',9,DATE_SUB(@today,INTERVAL 70 DAY),DATE_SUB(@today,INTERVAL 40 DAY),30,3,2,2,2,3468468468.47,381531531.53,3850000000.00,0,3850000000.00,'overdue',@j5,4,2,NOW()),
(CONCAT('AP/',DATE_FORMAT(@today,'%m/%Y'),'/0004'),'BEP/2025/0304',10,DATE_SUB(@today,INTERVAL 105 DAY),DATE_SUB(@today,INTERVAL 75 DAY),30,3,2,2,2,1891891891.89,208108108.11,2100000000.00,0,2100000000.00,'overdue',@j5,4,2,NOW()),
(CONCAT('AP/',DATE_FORMAT(@today,'%m/%Y'),'/0005'),'WPT/INV/2024/0921',6,DATE_SUB(@today,INTERVAL 180 DAY),DATE_SUB(@today,INTERVAL 150 DAY),30,5,1,4,4,1171171171.17,128828828.83,1300000000.00,0,1300000000.00,'overdue',@j4,4,2,NOW())
ON DUPLICATE KEY UPDATE `sisa` = VALUES(`sisa`);

-- ---------------------------------------------------------------------
-- 4a. RINCIAN TAGIHAN, RINCIAN INVOICE, PENGAKUAN PENDAPATAN
-- ---------------------------------------------------------------------
DELETE FROM `ap_invoice_items`;
INSERT INTO `ap_invoice_items` (`invoice_id`,`keterangan`,`coa_id`,`jumlah`,`ppn`,`total`)
SELECT a.id,
       CONCAT('Tagihan vendor ', a.nomor_supplier),
       81,
       a.subtotal,
       a.ppn,
       a.total
FROM `ap_invoices` a;

DELETE FROM `ar_invoice_items`;
INSERT INTO `ar_invoice_items` (`invoice_id`,`keterangan`,`coa_id`,`jumlah`,`ppn`,`total`)
SELECT a.id,
       a.deskripsi,
       69,
       a.subtotal,
       a.ppn,
       a.total
FROM `ar_invoices` a;

INSERT INTO `revenue_recognitions` (`nomor`,`project_id`,`contract_id`,`periode_id`,`tanggal`,`persentase`,`nilai`,`metode`,`status`,`keterangan`,`created_by`) VALUES
(CONCAT('REV/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),1,1,@periode,DATE_SUB(@today,INTERVAL 45 DAY),25.00,12000000000.00,'persentase_penyelesaian','approved','Progres fisik 25% berdasarkan berita acara pengukuran',4),
(CONCAT('REV/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),3,3,@periode,DATE_SUB(@today,INTERVAL 20 DAY),40.00,14400000000.00,'termin','submitted','Termin III pengangkutan batubara',4),
(CONCAT('REV/',DATE_FORMAT(@today,'%m/%Y'),'/0003'),2,2,@periode,@today,15.00,4125000000.00,'persentase_penyelesaian','draft','Draft pengakuan progres irigasi Way Sekampung',4)
ON DUPLICATE KEY UPDATE `nilai` = VALUES(`nilai`);

UPDATE `doc_sequences` SET `last_number` = 3 WHERE `code` = 'REV' AND `period_key` = DATE_FORMAT(@today,'%Y');

-- ---------------------------------------------------------------------
-- 5. RKAP & KOMITMEN
-- ---------------------------------------------------------------------
INSERT INTO `budgets` (`id`,`nomor`,`tahun`,`versi`,`judul`,`status`,`total_pendapatan`,`total_beban`,`catatan`,`created_by`,`approved_by`,`approved_at`) VALUES
(1,CONCAT('RKAP/',@tahun,'/01'),@tahun,1,CONCAT('RKAP ',@tahun,' PT. Lampung Jasa Utama'),'aktif',235000000000.00,195000000000.00,'Anggaran induk tahun berjalan',2,1,NOW()),
(2,CONCAT('RKAP-REV-04/',@tahun),@tahun,2,CONCAT('Revisi IV RKAP ',@tahun),'submitted',235000000000.00,196500000000.00,'Pergeseran sub-akun divisi teknologi informasi',3,NULL,NULL)
ON DUPLICATE KEY UPDATE `judul` = VALUES(`judul`);

INSERT INTO `budget_lines` (`budget_id`,`coa_id`,`jenis`,`bidang_id`,`divisi_id`,`business_unit_id`,`cost_center_id`,
 `m01`,`m02`,`m03`,`m04`,`m05`,`m06`,`m07`,`m08`,`m09`,`m10`,`m11`,`m12`,`total`) VALUES
-- Pendapatan
(1,69,'pendapatan',1,4,1,4, 11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,11666666666.67,140000000000.00),
(1,70,'pendapatan',2,2,2,2, 5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,5000000000.00,60000000000.00),
(1,71,'pendapatan',3,5,3,5, 2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,2916666666.67,35000000000.00),
-- Beban Divisi Konstruksi & Proyek Sipil (pagu 85 M)
(1,81,'beban',1,4,1,4, 4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,4166666666.67,50000000000.00),
(1,82,'beban',1,4,1,4, 1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,20000000000.00),
(1,83,'beban',1,4,1,4, 1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,15000000000.00),
-- Beban Operasional & Pemeliharaan (pagu 55 M)
(1,101,'beban',2,2,2,2, 1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,20000000000.00),
(1,84,'beban',2,2,2,2, 1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,1250000000.00,15000000000.00),
(1,93,'beban',2,2,2,2, 1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,1666666666.67,20000000000.00),
-- Beban Teknologi Informasi & Digital (pagu 22 M)
(1,96,'beban',5,8,NULL,8, 1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,1000000000.00,12000000000.00),
(1,91,'beban',5,8,NULL,8, 500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,500000000.00,6000000000.00),
(1,94,'beban',5,8,NULL,8, 333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,4000000000.00),
-- Beban Komersial & Pengembangan Bisnis (pagu 18 M)
(1,100,'beban',3,5,3,5, 833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,833333333.33,10000000000.00),
(1,90,'beban',3,5,3,5, 666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,8000000000.00),
-- Beban Umum, Legal & SDM (pagu 15 M)
(1,88,'beban',4,1,NULL,1, 666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,666666666.67,8000000000.00),
(1,92,'beban',4,1,NULL,1, 333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,333333333.33,4000000000.00),
(1,95,'beban',4,1,NULL,1, 250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,250000000.00,3000000000.00);

INSERT INTO `budget_commitments` (`nomor_po`,`budget_id`,`partner_id`,`coa_id`,`project_id`,`bidang_id`,`divisi_id`,`cost_center_id`,`tanggal`,`nilai`,`status`,`keterangan`) VALUES
('PO-2025-0891',1,6,81,1,1,4,4,DATE_SUB(@today,INTERVAL 12 DAY),3450000000.00,'open','Pengadaan material struktur gedung regional'),
('PO-2025-0902',1,7,81,2,1,4,4,DATE_SUB(@today,INTERVAL 6 DAY),14750000000.00,'open','Pengadaan material irigasi way sekampung')
ON DUPLICATE KEY UPDATE `nilai` = VALUES(`nilai`);

-- ---------------------------------------------------------------------
-- 6. PENGAJUAN PERSETUJUAN (antrean eksekutif)
-- ---------------------------------------------------------------------
INSERT INTO `approval_requests` (`id`,`module`,`ref_id`,`nomor_dokumen`,`judul`,`amount`,`bidang_id`,`divisi_id`,`project_id`,`requested_by`,`current_level`,`max_level`,`status`,`deadline`) VALUES
(1,'ap_payment',1,'PO-2025-0891','Pembayaran Termin Vendor Proyek',3450000000.00,1,4,1,6,1,2,'pending',DATE_ADD(NOW(),INTERVAL 2 HOUR)),
(2,'budget',2,'RKAP-REV-04','Revisi Anggaran Pergeseran Sub-Akun',820000000.00,5,8,NULL,3,1,2,'pending',DATE_ADD(@today,INTERVAL 5 HOUR)),
(3,'cash_advance',1,'PV-2025-0412','Uang Muka Operasional Lapangan (Subang)',145000000.00,2,2,3,4,1,1,'pending',DATE_ADD(@today,INTERVAL 30 HOUR)),
(4,'journal',NULL,'TAX-WTH-1025','Penyetoran PPh Pasal 23 & PPh Final',412380000.00,4,3,NULL,2,1,1,'pending',DATE_ADD(DATE_ADD(@today,INTERVAL 5 DAY),INTERVAL 9 HOUR)),
(5,'ar_invoice',1,'AR/2025/0001','Invoice Material Ketenagalistrikan Termin I',15000000000.00,3,5,4,4,1,1,'pending',DATE_ADD(@today,INTERVAL 20 HOUR)),
(6,'expense',1,'EXP-2025-0231','Biaya Perjalanan Dinas Monitoring Proyek',18500000.00,1,4,1,4,1,1,'approved',DATE_SUB(NOW(),INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE `judul` = VALUES(`judul`);

INSERT INTO `approval_actions` (`request_id`,`level`,`approver_user_id`,`action`,`notes`,`acted_at`) VALUES
(6,1,2,'approve','Sesuai dengan pagu perjalanan dinas divisi',DATE_SUB(NOW(),INTERVAL 2 DAY));

-- Delegasi persetujuan (pelimpahan sementara)
INSERT INTO `approval_delegations` (`from_user_id`,`to_user_id`,`module`,`tanggal_mulai`,`tanggal_selesai`,`alasan`,`is_active`,`created_by`)
SELECT 2, 3, NULL, DATE_SUB(@today, INTERVAL 2 DAY), DATE_ADD(@today, INTERVAL 5 DAY),
       'Dinas luar kota — persetujuan keuangan diwakilkan', 1, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `approval_delegations` d
    WHERE d.from_user_id = 2 AND d.to_user_id = 3 AND d.alasan LIKE 'Dinas luar kota%'
);

INSERT INTO `approval_delegations` (`from_user_id`,`to_user_id`,`module`,`tanggal_mulai`,`tanggal_selesai`,`alasan`,`is_active`,`created_by`)
SELECT 1, 2, 'budget', DATE_ADD(@today, INTERVAL 10 DAY), DATE_ADD(@today, INTERVAL 17 DAY),
       'Cuti tahunan Direktur Utama', 1, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `approval_delegations` d
    WHERE d.from_user_id = 1 AND d.to_user_id = 2 AND d.alasan = 'Cuti tahunan Direktur Utama'
);

-- ---------------------------------------------------------------------
-- 7. ASET TETAP
-- ---------------------------------------------------------------------
INSERT INTO `fixed_assets` (`kode`,`nama`,`category_id`,`tanggal_perolehan`,`harga_perolehan`,`nilai_residu`,`umur_bulan`,`akumulasi_penyusutan`,`nilai_buku`,`lokasi`,`bidang_id`,`divisi_id`,`status`) VALUES
('AST/2023/0001','Gedung Kantor Pusat LJU',1,DATE_SUB(@today,INTERVAL 36 MONTH),18000000000.00,0,240,2700000000.00,15300000000.00,'Bandar Lampung',4,1,'aktif'),
('AST/2023/0002','Toyota Hilux Double Cabin BE 1234 XY',2,DATE_SUB(@today,INTERVAL 30 MONTH),520000000.00,52000000.00,60,234000000.00,233000000.00,'Pool Kendaraan',2,2,'aktif'),
('AST/2023/0003','Excavator Komatsu PC200',3,DATE_SUB(@today,INTERVAL 28 MONTH),2350000000.00,235000000.00,96,617187500.00,1496875000.00,'Site Way Sekampung',1,4,'aktif'),
('AST/2024/0011','Server & Infrastruktur Data Center',3,DATE_SUB(@today,INTERVAL 14 MONTH),780000000.00,0,96,113750000.00,666250000.00,'Ruang Server',5,8,'aktif'),
('AST/2024/0012','Furnitur & Inventaris Kantor Lantai 2',4,DATE_SUB(@today,INTERVAL 10 MONTH),340000000.00,0,48,70833333.00,269166667.00,'Kantor Pusat',4,1,'aktif'),
('AST/2022/0007','Tanah Gudang Pergudangan Natar',5,DATE_SUB(@today,INTERVAL 48 MONTH),6500000000.00,0,0,0.00,6500000000.00,'Natar, Lampung Selatan',2,2,'aktif')
ON DUPLICATE KEY UPDATE `nilai_buku` = VALUES(`nilai_buku`);

-- Kartu mutasi: perolehan tiap aset + satu perpindahan lokasi.
DELETE FROM `asset_mutations`;
INSERT INTO `asset_mutations` (`asset_id`,`tipe`,`tanggal`,`keterangan`,`nilai`,`dari_lokasi`,`ke_lokasi`) VALUES
(1,'perolehan',DATE_SUB(@today,INTERVAL 36 MONTH),'Perolehan gedung kantor pusat',18000000000.00,NULL,'Bandar Lampung'),
(2,'perolehan',DATE_SUB(@today,INTERVAL 30 MONTH),'Pembelian kendaraan operasional',520000000.00,NULL,'Pool Kendaraan'),
(3,'perolehan',DATE_SUB(@today,INTERVAL 28 MONTH),'Perolehan alat berat via PT Sinar Teknik',2350000000.00,NULL,'Gudang Natar'),
(3,'mutasi_lokasi',DATE_SUB(@today,INTERVAL 10 MONTH),'Pemindahan untuk pekerjaan pipa induk',0.00,'Gudang Natar','Site Way Sekampung'),
(4,'perolehan',DATE_SUB(@today,INTERVAL 14 MONTH),'Pengadaan server & jaringan',780000000.00,NULL,'Ruang Server'),
(5,'perolehan',DATE_SUB(@today,INTERVAL 10 MONTH),'Pengadaan furnitur kantor',340000000.00,NULL,'Kantor Pusat'),
(6,'perolehan',DATE_SUB(@today,INTERVAL 48 MONTH),'Pembelian tanah gudang',6500000000.00,NULL,'Natar, Lampung Selatan');

-- Penyusutan periode sebelumnya (sudah diposting) untuk dua aset contoh.
INSERT INTO `asset_depreciations` (`asset_id`,`periode_id`,`tanggal`,`penyusutan`,`akumulasi`,`nilai_buku`,`status`) VALUES
(1,@periode,LAST_DAY(DATE_SUB(@today, INTERVAL 1 MONTH)),62500000.00,2700000000.00,15300000000.00,'posted'),
(3,@periode,LAST_DAY(DATE_SUB(@today, INTERVAL 1 MONTH)),18359375.00,617187500.00,1496875000.00,'posted'),
(5,@periode,LAST_DAY(DATE_SUB(@today, INTERVAL 1 MONTH)),7083333.00,70833333.00,269166667.00,'posted')
ON DUPLICATE KEY UPDATE `penyusutan` = VALUES(`penyusutan`);

-- ---------------------------------------------------------------------
-- 8. PAJAK
-- ---------------------------------------------------------------------
INSERT INTO `tax_documents` (`nomor`,`tipe`,`tax_type_id`,`partner_id`,`tanggal`,`periode_id`,`npwp`,`dpp`,`tarif`,`nilai_pajak`,`ref_module`,`ref_id`,`status`) VALUES
(CONCAT('010.000-',DATE_FORMAT(@today,'YY.MM'),'.00000001'),'faktur_keluaran',1,3,DATE_SUB(@today,INTERVAL 20 DAY),@periode,'01.003.004.5-013.000',13513513513.51,11.00,1486486486.49,'ar',1,'reported'),
(CONCAT('010.000-',DATE_FORMAT(@today,'YY.MM'),'.00000002'),'faktur_keluaran',1,1,DATE_SUB(@today,INTERVAL 50 DAY),@periode,'01.001.002.3-011.000',11009009009.01,11.00,1210099009.99,'ar',2,'reported'),
(CONCAT('WPT/BP-23/',DATE_FORMAT(@today,'%Y'),'/004'),'bukti_potong',4,6,DATE_SUB(@today,INTERVAL 25 DAY),@periode,'02.101.102.3-321.000',7207207207.21,2.00,144144144.14,'ap',1,'posted'),
(CONCAT('SSP-PPH23-',DATE_FORMAT(@today,'%Y%m')),'ssp',4,NULL,DATE_SUB(@today,INTERVAL 5 DAY),@periode,NULL,7207207207.21,2.00,144144144.14,NULL,NULL,'draft');

INSERT INTO `tax_reports` (`periode_id`,`jenis`,`total_dpp`,`total_pajak`,`total_kredit`,`kurang_bayar`,`status`) VALUES
(@periode,'ppn',24522512522.52,2697476378.03,1786000000.00,911476378.03,'draft'),
(@periode,'pph23',7207207207.21,144144144.14,0,144144144.14,'draft')
ON DUPLICATE KEY UPDATE `total_pajak` = VALUES(`total_pajak`);

-- ---------------------------------------------------------------------
-- 9. KAS & BANK, UANG MUKA, BIAYA
-- ---------------------------------------------------------------------
INSERT INTO `cash_transactions` (`nomor`,`tanggal`,`periode_id`,`tipe`,`bank_account_id`,`coa_lawan_id`,`project_id`,`bidang_id`,`divisi_id`,`cost_center_id`,`jumlah`,`metode`,`referensi`,`deskripsi`,`status`,`journal_id`,`created_by`,`approved_by`,`posted_at`) VALUES
(CONCAT('KM/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),DATE_SUB(@today,INTERVAL 12 DAY),@periode,'masuk',1,12,1,1,4,4,7500000000.00,'bank','TRF-BMRI-899120','Penerimaan termin piutang pelanggan','posted',@j11,4,2,NOW()),
(CONCAT('KK/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),DATE_SUB(@today,INTERVAL 9 DAY),@periode,'keluar',1,43,1,1,4,4,5000000000.00,'bank','BIFAST-772190','Pembayaran tagihan vendor proyek','posted',@j10,4,2,NOW()),
(CONCAT('KK/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),DATE_SUB(@today,INTERVAL 4 DAY),@periode,'keluar',1,15,3,2,2,2,145000000.00,'bank','PV-2025-0412','Pencairan uang muka kegiatan','posted',@j12,4,2,NOW()),
(CONCAT('KK/',DATE_FORMAT(@today,'%m/%Y'),'/0003'),DATE_SUB(@today,INTERVAL 2 DAY),@periode,'keluar',4,95,NULL,4,1,1,3250000.00,'kas','PC-2025-018','Konsumsi rapat koordinasi direksi','posted',NULL,4,2,NOW())
ON DUPLICATE KEY UPDATE `jumlah` = VALUES(`jumlah`);

INSERT INTO `cash_advances` (`nomor`,`tanggal`,`periode_id`,`pemohon_user_id`,`bagian`,`bidang_id`,`divisi_id`,`cost_center_id`,`project_id`,`keperluan`,`jumlah_diajukan`,`jumlah_disetujui`,`jumlah_dicairkan`,`jumlah_dipertanggungjawabkan`,`sisa`,`bank_account_id`,`status`,`created_by`,`approved_by`,`approved_at`) VALUES
(CONCAT('CA/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),DATE_SUB(@today,INTERVAL 6 DAY),@periode,4,'Operasional Proyek',2,2,2,3,'Uang muka operasional lapangan Subang',145000000.00,145000000.00,145000000.00,0.00,145000000.00,1,'cair',4,2,NOW()),
(CONCAT('CA/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),DATE_SUB(@today,INTERVAL 4 DAY),@periode,6,'Konstruksi',1,4,4,1,'Uang muka mobilisasi alat berat',250000000.00,250000000.00,0.00,0.00,0.00,1,'approved',6,2,NOW()),
(CONCAT('CA/',DATE_FORMAT(@today,'%m/%Y'),'/0003'),DATE_SUB(@today,INTERVAL 2 DAY),@periode,3,'IT',5,8,8,NULL,'Uang muka pembelian perangkat jaringan',48500000.00,0.00,0.00,0.00,0.00,NULL,'submitted',3,NULL,NULL)
ON DUPLICATE KEY UPDATE `keperluan` = VALUES(`keperluan`);

INSERT INTO `expense_requests` (`nomor`,`tanggal`,`periode_id`,`tipe`,`judul`,`pemohon_user_id`,`bidang_id`,`divisi_id`,`cost_center_id`,`project_id`,`bank_account_id`,`subtotal`,`ppn`,`pph`,`total`,`status`,`journal_id`,`created_by`,`approved_by`,`approved_at`) VALUES
(CONCAT('EXP/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),DATE_SUB(@today,INTERVAL 8 DAY),@periode,'perjalanan_dinas','Monitoring proyek way sekampung',4,1,4,4,2,2,18500000.00,2035000.00,0.00,20535000.00,'approved',NULL,4,2,NOW()),
(CONCAT('EXP/',DATE_FORMAT(@today,'%m/%Y'),'/0002'),DATE_SUB(@today,INTERVAL 3 DAY),@periode,'operasional','Pemeliharaan kendaraan operasional',4,2,2,2,3,1,7250000.00,797500.00,0.00,8047500.00,'dibayar',NULL,4,2,NOW())
ON DUPLICATE KEY UPDATE `judul` = VALUES(`judul`);

-- ---------------------------------------------------------------------
-- 10. AUDIT LOG, NOTIFIKASI, PENOMORAN
-- ---------------------------------------------------------------------
INSERT INTO `audit_logs` (`user_id`,`user_name`,`action`,`module`,`ref_table`,`ref_code`,`description`,`ip_address`,`created_at`) VALUES
(5,'Rina Kartika','post','journal','journal_entries',CONCAT('JU/',DATE_FORMAT(@today,'%m/%Y'),'/0009'),'Posting jurnal penyesuaian penyusutan aset tetap','10.20.0.15',DATE_SUB(NOW(),INTERVAL 35 MINUTE)),
(4,'Budi Darmawan','create','cash','cash_transactions',CONCAT('KM/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),'Pencairan kas masuk dari pelanggan PT PLN','10.20.0.22',DATE_SUB(NOW(),INTERVAL 4 HOUR)),
(2,'Siti Rahmayanti','approve','ap','ap_invoices',CONCAT('AP/',DATE_FORMAT(@today,'%m/%Y'),'/0001'),'Persetujuan tagihan vendor PT Wijaya Perkasa Teknik','10.20.0.10',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(4,'Budi Darmawan','update','tax','tax_documents',CONCAT('WPT/BP-23/',DATE_FORMAT(@today,'%Y'),'/004'),'Verifikasi pajak keluaran e-Faktur 18 dokumen','10.20.0.22',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(8,'Administrator Sistem','update','settings','app_settings','budget_alert_percent','Ambang peringatan anggaran diubah menjadi 80%','10.20.0.5',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(2,'Siti Rahmayanti','close','period','fiscal_periods',DATE_FORMAT(DATE_SUB(@today,INTERVAL 1 MONTH),'%Y-%m'),'Tutup buku bulanan selesai, saldo awal dipindahkan','10.20.0.10',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(6,'Agus Setiawan','create','budget','budgets',CONCAT('RKAP-REV-04/',@tahun),'Pengajuan revisi IV RKAP pergeseran sub-akun','10.20.0.31',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(1,'Bambang Hartono','export','report','reports','NERACA','Unduh PDF Neraca konsolidasi periode berjalan','10.20.0.2',DATE_SUB(NOW(),INTERVAL 3 DAY));

INSERT INTO `notifications` (`user_id`,`type`,`title`,`body`,`link`,`is_read`) VALUES
(1,'approval','5 dokumen menunggu persetujuan','Terdapat 5 pengajuan keuangan yang membutuhkan otorisasi Level 1.','approval','0'),
(1,'warning','Piutang jatuh tempo meningkat','Piutang > 30 hari mencapai 13,7% dari total piutang usaha.','ar','0'),
(2,'success','Tutup buku bulan lalu selesai','Periode sebelumnya berhasil ditutup dan dikunci.','period','1');

UPDATE `doc_sequences` SET `last_number` = 12 WHERE `code` = 'JU' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 3  WHERE `code` = 'KK' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 1  WHERE `code` = 'KM' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 5  WHERE `code` = 'AR' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 5  WHERE `code` = 'AP' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 3  WHERE `code` = 'CA' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 2  WHERE `code` = 'EXP' AND `period_key` = DATE_FORMAT(@today,'%Y');
UPDATE `doc_sequences` SET `last_number` = 2  WHERE `code` = 'RKAP' AND `period_key` = DATE_FORMAT(@today,'%Y');
