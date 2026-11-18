# LJU Finance & Accounting System

Sistem informasi keuangan dan akuntansi **PT. Lampung Jasa Utama** — dibangun dengan **PHP native + MySQL**
tanpa framework, tanpa Composer, dan tanpa dependensi PDF eksternal, dirancang untuk dijalankan di
`https://finance.lju.co.id`.

Repositori ini sedang dibangun **bertahap (8 tahap)**. Status rinci tiap tahap ada di
[`projects.md`](projects.md).

| Tahap | Tema | Status |
|-------|------|--------|
| 1 | Fondasi, Master Data & Executive Dashboard | ✅ Selesai |
| 2 | Akuntansi Inti (jurnal, posting, tutup buku) | ✅ Selesai |
| 3 | Kas & Bank, Uang Muka, Biaya | ✅ Selesai |
| 4 | Hutang & Piutang | ✅ Selesai |
| 5 | Anggaran & Approval Center | ✅ Selesai |
| 6 | Aset Tetap & Perpajakan | ✅ Selesai |
| 7 | Pelaporan Keuangan & PDF | ✅ Selesai |
| 8 | Integrasi & Hardening | ✅ Selesai |
| 9 | Hardening Lanjutan & Operasional | ✅ Selesai |

> **Pratinjau desain statis:** folder `preview/` berisi HTML hasil render nyata dari view PHP
> (layout, partial, dan CSS yang sama) dengan data contoh: `dashboard`, `login`, `journal-index`,
> `journal-form`, `journal-show`, `ledger`, `trial-balance`, `closing`, `cash-index`, `cash-form`,
> `cash-show`, `advance-index`, `advance-form`, `advance-show`, `expense-index`, `expense-form`,
> `expense-show`, `recon-index`, `recon-form`, `recon-show`, `ap-index`, `ap-form`, `ap-show`,
> `ar-index`, `ar-form`, `ar-show`, `revenue-index`, `revenue-form`, `revenue-show`,
> `budget-index`, `budget-form`, `budget-show`, `budget-monitoring`, `budget-commitments`,
> `approval-index`, `approval-show`, `approval-delegations`, `asset-index`, `asset-form`, `asset-show`,
> `asset-depreciation`, `tax-index`, `tax-form`, `tax-show`, `tax-report`, `report-index`, `report-income`,
> `report-balance-sheet`, `report-cash-flow`, `report-equity`, `report-project`, `report-unit-usaha`, `report-print`,
> `project-index`, `project-show`, `project-form`, `project-contracts`, `project-contract-form`, `project-contract-show`,
> `inventory-index`, `inventory-show`, `inventory-form`, `inventory-movements`, `inventory-masuk`, `inventory-keluar`,
> `procurement-index`, `procurement-form`, `procurement-show`, `procurement-terima`, `system-backup`. Total 70 halaman. Berguna untuk
> meninjau tampilan tanpa server PHP/MySQL.

---

## 1. Fitur Tahap 1

**Fondasi teknis**

- Front controller tunggal (`index.php`) + autoloader PSR-4 sederhana (`Lju\` → `app/`).
- Mini-framework: `Config`, `Db` (PDO, prepared statement, transaksi), `Router` (query-string & pretty URL),
  `Auth` (sesi SSO `LJU_APPS` + sinkronisasi user), `View`, `Csrf`, `Flash`, `Validator`, `Audit`, `Paginator`.
- Helper global: `url()`, `asset()`, `e()`, `rupiah()`, `tanggal()`, `badge()`, `icon()`, `setting()`, dll.
- Icon set SVG inline (`app/Support/icons.php`) — tanpa dependensi aset eksternal.
- Installer berbasis web (`install.php`) dengan token, pemeriksaan kebutuhan sistem, import skema + seed,
  dan penulisan `config/config.local.php`.
- Proteksi `.htaccess`: folder internal ditolak, folder `uploads/` tidak mengeksekusi skrip.

**Master data (CRUD penuh)**

- Chart of Accounts (109 akun, hierarki 4 level, tipe/kategori, arus kas, akun terkontrol).
- Bidang & Divisi (istilah tampilan: `division` → **Nama Bidang**, `department` → **Nama Divisi**).
- Cost Center, Unit Usaha, Vendor & Customer, Proyek, Rekening Bank.
- Periode Akuntansi (tahun buku + 12 periode, status buka/tutup/kunci).

**Dashboard eksekutif**

- 4 KPI: likuiditas, piutang, hutang, serapan anggaran + strip ringkasan (aset, kewajiban, ekuitas,
  pendapatan, beban, laba).
- Realisasi RKAP per divisi (pagu, realisasi, komitmen, sisa) + total komitmen.
- Saldo kas & bank per rekening (dengan indikator saldo minimum).
- Matriks aging piutang vs hutang dalam 4 bucket + current ratio.
- Antrean persetujuan dengan indikator SLA, feed audit terbaru.

**Akuntansi inti (Tahap 2)**

- Jurnal umum multi-baris dengan editor baris dinamis, validasi debit = kredit, dan dimensi laporan
  (bidang, divisi, proyek, cost center, vendor) per baris.
- Alur persetujuan: draft → diajukan → disetujui/ditolak → **posted**, dengan riwayat proses dan alasan penolakan.
- Posting atomik + pembalik otomatis (jurnal asal ditandai `reversed`, tidak lagi dihitung di saldo).
- Buku besar per akun (saldo awal, mutasi berjalan, saldo akhir) + rekap buku besar pembantu per dimensi.
- Neraca saldo per periode atau kumulatif tahun buku, lengkap dengan pemeriksaan keseimbangan.
- Tutup buku bulanan (validasi jurnal belum posted, carry-forward saldo), kunci periode, buka kembali
  periode `closed` dengan alasan wajib, dan tutup buku tahunan dengan jurnal penutup otomatis.
- Bukti transaksi: unggah/unduh/hapus lampiran per jurnal (tipe & ukuran divalidasi, unduhan butuh login).

**Kas & bank, uang muka, biaya (Tahap 3)**

- **Kas & Bank**: transaksi kas masuk, kas keluar, dan transfer antar rekening dengan penomoran otomatis
  `KM/KK/TRF`, kartu saldo per rekening (bank, escrow, petty cash), filter status/tipe/rekening, dan
  ringkasan tahun berjalan.
- Alur persetujuan transaksi: draft → diajukan → disetujui/ditolak → **posted**; posting membuat jurnal
  otomatis dan memperbarui `saldo_berjalan` rekening dalam satu transaksi DB.
- Jurnal otomatis: masuk **D Bank / K akun lawan**, keluar **D akun lawan / K Bank**, transfer
  **D Bank tujuan / K Bank asal** — dengan dimensi bidang/divisi/proyek/cost center.
- **Uang muka kegiatan** (`CA`): pengajuan → persetujuan (jumlah disetujui) → pencairan (D Uang Muka /
  K Bank) → pertanggungjawaban per akun beban → penyelesaian dengan pengembalian sisa atau pelunasan
  kekurangan dana.
- **Pengajuan biaya** (`EXP`): rincian multi-baris (COA beban, PPN, PPh, no. bukti), total = subtotal +
  PPN − PPh, persetujuan, lalu pembayaran dengan jurnal D beban + D PPN Masukan / K utang PPh + K Bank.
- **Rekonsiliasi bank**: saldo buku otomatis dari jurnal posted per rentang tanggal, baris rekening koran
  manual, penandaan cocok, rekap selisih, penyelesaian dengan catatan wajib bila masih berselisih.
- Editor baris dinamis (`assets/js/transaksi.js`) untuk rincian biaya/uang muka/rekening koran, termasuk
  perhitungan total dan pengisian pajak otomatis dari tarif.

**Hutang & piutang (Tahap 4)**

- **Hutang usaha (AP)**: tagihan vendor `AP` dengan rincian akun beban/aset, PPN masukan per baris, termin
  dan jatuh tempo otomatis dari master vendor, alur draft → diajukan → disetujui → **posted**, serta
  pembayaran sebagian/penuh `APP` dengan potongan PPh.
- **Piutang usaha (AR)**: invoice pelanggan `AR` (opsional terhubung kontrak & termin) dengan rincian akun
  pendapatan dan PPN keluaran, persetujuan, posting, dan penerimaan `ARP` termasuk PPh yang dipotong pelanggan.
- Jurnal otomatis: AP **D beban + D PPN Masukan / K Utang Usaha**, pembayaran **D Utang Usaha / K Bank +
  K Utang PPh**; AR **D Piutang Usaha / K Pendapatan + K PPN Keluaran**, penerimaan **D Bank + D PPh
  Dibayar Dimuka / K Piutang Usaha**.
- **Pengakuan pendapatan** `REV`: metode persentase penyelesaian, termin, dan kontrak selesai; nilai
  dihitung dari nilai kontrak dikurangi yang sudah diakui dan ditolak bila melebihi nilai kontrak; jurnal
  **D Piutang Belum Ditagih / K Pendapatan Usaha**. Penagihan atas bagian yang sudah diakui mengkredit
  `1-1330` sehingga pendapatan tidak diakui dua kali.
- **Aging** hutang dan piutang 5 bucket (belum jatuh tempo, 1–30, 31–60, 61–90, > 90 hari) di halaman daftar,
  status `overdue` dihitung otomatis saat tampil.
- Form AP/AR memakai editor baris dinamis dengan total berjalan dan jatuh tempo otomatis (`transaksi.js`).

**Anggaran & pengendalian (Tahap 5)**

- **RKAP berversi**: penyusunan pagu pendapatan & beban per akun dan dimensi (bidang, divisi, unit usaha,
  cost center, proyek), nomor otomatis `RKAP/<tahun>/NN`, penyebaran 12 bulan, alur
  draft → diajukan → disetujui → **aktif** → arsip, serta **revisi** `RKAP-REV-NN/<tahun>` yang menyalin
  baris versi sebelumnya (hanya satu versi aktif per tahun).
- **Monitoring realisasi**: pagu vs realisasi jurnal posted s.d. bulan terpilih vs komitmen, sisa pagu,
  persentase serapan, status `aman`/`waspada`/`over` (ambang `budget_alert_percent`), rekap per divisi,
  dan rincian bulanan 12 kolom.
- **Komitmen pengadaan**: PO/kontrak pengadaan (nomor PO unik, vendor, akun, proyek/divisi) dengan status
  `open`/`sebagian`/`closed`/`cancelled`; komitmen terbuka mengurangi pagu tersedia.
- **Approval Center**: antrean persetujuan seluruh modul (jurnal, kas, uang muka, biaya, AP, AR, pendapatan,
  RKAP) dengan SLA per level dari `approval_flows`, aksi setujui/tolak/kembalikan/delegasi, otorisasi
  pejabat per level, notifikasi in-app, riwayat aksi, statistik ketepatan waktu, serta **delegasi**
  per modul & rentang tanggal.
- Status dokumen modul tidak berubah oleh Approval Center; posting/void tetap hanya dari modul asal.

**Aset tetap & perpajakan (Tahap 6)**

- **Kartu inventaris aset**: kode otomatis `AST/<tahun>/NNNN`, kategori menentukan umur ekonomis, metode
  penyusutan, dan akun COA; lokasi, penanggung jawab, invoice, serta dimensi bidang/divisi/proyek.
  Alur draft → **aktifkan** (jurnal **D akun aset / K bank** atau **K Utang Usaha**) → nonaktif/dijual/dihapus.
- **Penyusutan otomatis**: garis lurus, saldo menurun ganda, jumlah angka tahun, dan unit produksi (fallback
  garis lurus). Penyusutan dimulai bulan berikutnya setelah perolehan, berhenti di akhir umur ekonomis atau
  saat nilai buku = nilai residu. Hitung → simpan draft → **posting** menghasilkan satu jurnal agregat per
  kategori (**D Beban Penyusutan / K Akumulasi Penyusutan**) dan memperbarui nilai buku seluruh aset.
- **Mutasi & pelepasan**: perpindahan lokasi bertimeline, **penjualan** (D Bank + D Akumulasi / K Aset dan
  laba `4-2200` atau rugi `5-3900`), serta penghapusan aset dengan alasan.
- **Dokumen pajak**: faktur keluaran/masukan, bukti potong, SSP, dan dokumen lain dengan tarif otomatis dari
  master jenis pajak, DPP, nilai pajak, NPWP, partner, dan tautan `ref_module`/`ref_id` ke AP/AR/biaya.
  SSP dapat disetorkan (**D utang pajak / K bank**) lalu ditandai dilaporkan atau di-void.
- **Rekap & SPT**: rekap bulanan per tipe dan per jenis pajak, perhitungan **SPT PPN** (keluaran − masukan)
  serta PPh 21/23/4(2)/badan, status `draft/final/dilaporkan` dengan tanggal lapor dan referensi.
- **e-Faktur**: daftar faktur keluaran periode terpilih dan unduhan CSV `FK;NPWP;Nama;Tanggal;DPP;Tarif;PPN;Referensi`
  siap diimpor ke aplikasi e-Faktur DJP.

**Pelaporan keuangan & PDF (Tahap 7)**

- **Lima laporan wajib**: laba rugi (laba kotor/operasional/sebelum pajak/bersih + margin), neraca
  (aset lancar/tetap/lain, kewajiban, ekuitas + indikator seimbang), arus kas metode langsung
  (operasi/investasi/pendanaan + rekonsiliasi), perubahan ekuitas (saldo awal, laba, setoran/prive,
  saldo akhir), dan laba rugi per proyek.
- **Periode & pembanding**: bulanan atau s.d. tahun berjalan (YTD), filter bidang/divisi/proyek,
  kolom pembanding periode sebelumnya beserta Δ%.
- **Rasio & tren**: rasio lancar, rasio kas, debt-to-equity, net profit margin, ROA, ROE, dan grafik
  tren pendapatan vs beban enam bulan terakhir pada hub laporan.
- **Ekspor & cetak**: unduhan **PDF** (penulis bawaan tanpa Dompdf), **XLSX**, dan **CSV** dengan audit
  log, serta halaman cetak A4 berkop lengkap dengan catatan dan tiga kolom tanda tangan.

**Pengadaan, persediaan & proyek (Tahap 8)**

- **Purchase order**: PO multi-baris untuk barang persediaan maupun jasa, dimensi proyek/unit/bidang/divisi/
  cost center, alur draft → diajukan → disetujui → diterima sebagian → selesai, serta pembatalan beralasan.
- **Penerimaan barang**: GR per PO menambah stok, memperbarui harga rata-rata bergerak, dan menyinkronkan
  status PO; baris jasa tidak menahan penyelesaian.
- **Tagihan vendor**: tarik tagihan sekali per PO menjadi draft AP sesuai qty diterima, dengan tautan balik
  `ap_invoice_id` dan komitmen anggaran otomatis (`budget_commitments`).
- **Persediaan**: master barang & gudang, kartu stok, penerimaan manual, pengeluaran dengan jurnal
  **D beban / K persediaan**, peringatan stok minimum, dan penomoran `INV`.
- **Proyek & kontrak**: portofolio dengan laba/margin/progres, kontrak bertermin, penagihan termin menjadi
  draft AR, komposisi biaya, dan riwayat pengakuan pendapatan.
- **Laporan unit usaha**: konsolidasi pendapatan, beban, laba, aset, dan kewajiban per unit beserta
  kontribusi; filter unit tersedia di seluruh laporan keuangan.

**Hardening & operasional (Tahap 9)**

- **Hard stop anggaran**: bila diaktifkan, jurnal otomatis dan posting manual ditolak ketika nilai beban
  melebihi pagu RKAP aktif; pesan menyebut akun, pagu, realisasi, dan nilai transaksi.
- **Cadangan database**: buat, unduh, dan hapus dari **System Admin → Cadangan Database**; retensi otomatis
  (default 14 hari), file SQL mandiri, serta skrip `bin/backup.php` untuk cron.
- **Dokumentasi pengguna**: `docs/panduan-pengguna.md` mencakup alur harian, pengadaan, persediaan,
  proyek/kontrak, hard stop, tutup buku, dan prosedur pemulihan.

**Administrasi sistem**

- Pengguna & hak akses (9 role, permission granular `modul.aksi`).
- Matriks permission per role (Role & Permission).
- Audit trail dengan filter modul/aksi/tanggal dan diff nilai lama/baru.
- Pengaturan aplikasi per grup, profil pengguna + sinkronisasi ulang SSO.

---

## 2. Kebutuhan Sistem

| Komponen | Versi / catatan |
|----------|-----------------|
| PHP | 8.0 atau lebih baru (diuji pada 8.2) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo` |
| MySQL / MariaDB | 5.7+ / 10.3+ (utf8mb4) |
| Web server | Apache 2.4 (`mod_rewrite`, `.htaccess`) atau Nginx |
| PDF | tidak perlu pustaka eksternal; `Lju\Core\Pdf` menulis PDF langsung (Dompdf tidak dipakai) |
| ZIP (opsional) | ekstensi `zip` mempercepat XLSX; tanpa ekstensi tetap dihasilkan lewat writer ZIP bawaan |

---

## 3. Instalasi

1. **Unggah** seluruh repositori ke document root (mis. `public_html`).
2. **Tanpa dependensi PDF eksternal** — laporan PDF memakai penulis bawaan `app/Core/Pdf.php`.
3. **Siapkan database** secara manual (opsional — installer bisa membuatnya):

   ```sql
   CREATE DATABASE lju_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'lju_app'@'localhost' IDENTIFIED BY 'kata-sandi-kuat';
   GRANT ALL PRIVILEGES ON lju_finance.* TO 'lju_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

   Alternatif manual: import berurutan `database/schema/01_foundation.sql` … `07_*.sql`,
   lalu `database/seed/01_reference.sql` dan (opsional) `database/seed/02_demo.sql`.

   Jika database sudah terpasang **sebelum** Tahap 3, jalankan juga
   `database/migrations/2026-11-17_tahap3_cash_permissions.sql` agar permission
   `cash.approve` / `cash.post` tersedia (idempotent).

   Database yang dipasang **sebelum** Tahap 4 perlu menjalankan
   `database/migrations/2026-11-17_tahap4_ap_ar.sql` agar akun `1-1330`, `1-1440`, dan prefix `REV`
   tersedia (idempotent).

   Database yang dipasang **sebelum** Tahap 5 perlu menjalankan
   `database/migrations/2026-11-17_tahap5_budget_approval.sql` agar permission
   `budget.*`/`approval.*`, setting ambang anggaran, alur `AF-BUD-*`, dan prefix `RKAP` tersedia
   (idempotent).

   Database yang dipasang **sebelum** Tahap 6 perlu menjalankan
   `database/migrations/2026-11-18_tahap6_asset_tax.sql` agar permission `asset.*`/`tax.*`, setting
   aset & pajak, dan prefix `AST`/`DEP`/`TAX` tersedia (idempotent).

   Database yang dipasang **sebelum** Tahap 7 perlu menjalankan
   `database/migrations/2026-11-18_tahap7_report.sql` agar permission `report.view`/`report.export`
   dan setting kop/tanda tangan laporan tersedia (idempotent, tanpa tabel baru).

   Database yang dipasang **sebelum** Tahap 8 perlu menjalankan
   `database/migrations/2026-11-18_tahap8_integration.sql` agar tabel pengadaan/persediaan, permission
   `procurement.*`/`inventory.*`, prefix `PO`/`GR`/`INV`, dan kolom audit kontrak tersedia (idempotent).

   Database yang dipasang **sebelum** Tahap 9 perlu menjalankan
   `database/migrations/2026-11-18_tahap9_hardening.sql` agar setting retensi cadangan dan hard stop
   anggaran tersedia (idempotent, tanpa tabel baru).

4. **Jalankan installer** dengan token (default ada di `config/config.php` → `security.install_token`):

   ```
   https://domain-anda/install.php?token=lju-install-2025
   ```

   Isi kredensial database, centang *import data contoh* bila ingin dashboard langsung berisi,
   lalu **hapus `install.php`** setelah selesai.

5. **Verifikasi** — buka aplikasi. Jika SSO belum terhubung, aktifkan mode pengembangan:

   ```php
   // config/config.local.php
   'app' => ['env' => 'local', 'debug' => true],
   'sso' => ['dev_login' => true],
   ```

   Halaman login pengembangan akan menampilkan daftar pengguna seed untuk dipilih.

6. **Produksi**: set `app.env = production`, `app.debug = false`, `sso.dev_login = false`,
   ganti `security.install_token`, dan pastikan folder `storage/` serta `uploads/` writable.

---

## 4. Konfigurasi

Prioritas: `config/config.php` → ditimpa `config/config.local.php` (dibuat installer, tidak di-commit).

| Kunci | Arti |
|-------|------|
| `app.env` | `local` / `production` — mengaktifkan perilaku khusus pengembangan |
| `app.debug` | `true` menampilkan detail error (hanya untuk pengembangan) |
| `app.base_url` | Kosong = dideteksi otomatis dari request |
| `app.pretty_url` | `true` = URL tanpa `index.php?r=` (butuh `mod_rewrite`) |
| `db.*` | host, port, nama database, user, password |
| `sso.*` | `session_name` (`LJU_APPS`), `db`, `user_table`, `enabled`, `dev_login` |
| `security.install_token` | Token akses `install.php` |
| `upload.*`, `pdf_paper` | Batas ukuran/tipe lampiran dan ukuran kertas PDF laporan |

Konfigurasi sensitif tidak boleh di-commit — lihat `.gitignore`.

---

## 5. Struktur Direktori

```
ljuFinance/
├── index.php                  # front controller
├── install.php                # installer berbasis web (hapus setelah dipakai)
├── .htaccess                  # rewrite, proteksi, header keamanan
├── config/
│   └── config.php             # konfigurasi utama
├── app/
│   ├── bootstrap.php          # konstanta, autoloader, sesi, error handler
│   ├── routes.php             # tabel rute
│   ├── Config/                # menu.php, resources.php
│   ├── Core/                  # Config, Db, Auth, Router, View, Csrf, Flash, Validator, Audit, Paginator, App
│   ├── Support/               # helpers.php, icons.php
│   ├── Services/              # Period, Numbering, Dashboard, Journal, Ledger, Closing, AccountMap,
│   │                          # Cash, Advance, Expense, BankRecon
│   ├── Controllers/           # Base, Master, Coa, Dashboard, Period, User, Role, Audit, Setting, Profile, Auth,
│   │                          # Journal, Ledger, TrialBalance, Closing, Cash, Advance, Expense,
│   │                          # Reconciliation, Placeholder, TransaksiTrait
│   └── Views/
│       ├── layouts/           # app.php (shell), blank.php (auth/error)
│       ├── partials/          # sidebar, topbar, flash, dashboard_head
│       └── pages/             # dashboard, master/*, coa/*, period, user, role, audit, settings, profile,
│                              # journal/{index,form,show}, ledger, trial-balance, closing,
│                              # cash/{index,form,show,reconciliation-*}, advance/*, expense/*, login, soon, error
├── assets/
│   ├── css/app.css            # design system (token, layout, komponen, editor jurnal)
│   ├── js/app.js              # sidebar, flash, konfirmasi, format uang, ⌘K
│   ├── js/journal.js          # editor baris jurnal, total & indikator keseimbangan
│   └── js/transaksi.js        # baris dinamis kas/uang muka/biaya/rekonsiliasi + total & pajak
├── database/
│   ├── schema/                # 60 tabel, 8 file
│   ├── seed/                  # 01_reference.sql, 02_demo.sql
│   └── migrations/            # patch idempotent untuk database yang sudah terpasang
├── bin/                       # skrip CLI (backup.php untuk cron)
├── docs/                      # panduan pengguna
├── preview/                   # HTML statis pratinjau desain
├── storage/                   # log & ekspor (tidak di-commit)
├── uploads/                   # lampiran (tidak di-commit)
└── vendor/                    # tidak diperlukan (PDF ditulis sendiri)
```

---

## 6. Autentikasi & Role

Aplikasi **tidak** menangani login sendiri. Portal SSO (`lju.co.id`) membuat sesi `LJU_APPS`; aplikasi
membaca kunci sesi berikut lalu menyinkronkan ke tabel `app_users`:

`user_id`, `username`, `name`, `email`, `nik`, `position`, `division` (→ `nama_bidang`),
`department` (→ `nama_divisi`), `role`, `photo`, `avatar`, `webmail_pass`.

Sembilan role aplikasi: `super_admin`, `dirut`, `dirops`, `kabid_keuangan`, `staff_accounting`,
`staff_finance`, `kepala_divisi`, `kepala_bidang`, `auditor`. Permission berformat `modul.aksi`
(mis. `journal.post`, `ap.approve`) dan diperiksa melalui `Auth::can()`.

---

## 7. Alur Kerja & Konvensi

- **Nomor dokumen** dibangkitkan `NumberingService` dari `doc_sequences`
  (prefix `JU, KK, KM, TRF, CA, EXP, AP, APP, AR, ARP, AST, DEP, TAX, RKAP, REV, PO, GR, INV`).
- **Uang** disimpan `DECIMAL(18,2)` dan ditampilkan `rupiah()`; input mata uang diformat di klien.
- **Waktu** disimpan UTC, ditampilkan Asia/Jakarta.
- **Audit** dicatat `Audit::log()` untuk semua aksi tulis (modul, aksi, ref, nilai lama/baru, IP).
- **CSRF** wajib pada setiap form POST (`csrf_field()` + `Csrf::verify()`).
- **Soft delete / status** memakai kolom `is_active` atau `status`, bukan `DELETE`.

---

## 8. Pengujian Cepat (smoke test)

**Tahap 1 — fondasi**

1. Buka `install.php?token=...` → import skema + seed demo.
2. Login (mode pengembangan) sebagai **Bambang Hartono** (Direktur Utama) → dashboard terisi data contoh.
3. Buka **Master Data → Chart of Accounts** → cari "kas", tambah akun anak, pastikan muncul di tabel.
4. Buka **Role & Permission** → centang/hapus satu permission, simpan → cek **Audit Trail**.
5. Ubah status satu periode di **Periode Akuntansi** → pastikan perubahan tercatat di audit.
6. Buka **Pengaturan** → ubah ambang anggaran, simpan, muat ulang.

**Tahap 2 — akuntansi**

7. Login sebagai **Budi Darmawan** (Staff Finance) → **Jurnal Umum → Jurnal Baru**; isi 2 baris
   (mis. Beban Material 1.000.000 D / Utang Usaha 1.000.000 K) → **Simpan Draft**.
8. Coba simpan jurnal tidak seimbang → indikator jendela menunjukkan selisih dan server menolak.
9. **Ajukan** jurnal → login sebagai **Siti Rahmayanti** (Kepala Bidang Keuangan) → **Setujui** → **Posting**.
10. Buka **Buku Besar** akun `5-1100 Beban Material Proyek` → saldo awal, mutasi, saldo berjalan muncul.
11. Buka **Neraca Saldo** → total debit = total kredit (badge "Neraca seimbang").
12. **Tutup Buku** → klik *Sinkronkan Saldo* → tutup satu periode yang tidak punya jurnal belum posted.
13. Posting jurnal lalu **Buat Pembalik** → pastikan jurnal asal berstatus `reversed` dan saldo kembali.

**Tahap 3 — kas & bank, uang muka, biaya**

14. **Kas & Bank → Transaksi Baru**: buat kas keluar 1.000.000 (beban operasional) → ajukan → setujui →
    posting → cek saldo rekening berkurang dan jurnal `KK/...` terbentuk dengan **D beban / K bank**.
15. Buat **transfer** antar rekening → pastikan saldo asal berkurang dan saldo tujuan bertambah sebesar
    nominal yang sama, tanpa akun beban/pendapatan.
16. **Uang Muka → Pengajuan Baru** (mis. 5.000.000) → ajukan → setujui → **Cairkan** (jurnal D Uang Muka /
    K Bank) → isi 2 rincian pertanggungjawaban → **Selesaikan** → sisa dana masuk kembali ke rekening.
17. **Pengajuan Biaya → Baru**: isi rincian dengan PPN 11% dan PPh 2% → ajukan → setujui → **Bayar** →
    periksa jurnal: D beban, D PPN Masukan, K utang PPh, K bank (total bersih).
18. **Rekonsiliasi Bank → Baru**: pilih rekening + rentang tanggal → masukkan saldo rekening koran dan
    satu mutasi bank → cocokkan baris → selesaikan (isi catatan bila masih ada selisih).

**Tahap 4 — hutang & piutang**

19. **Hutang Usaha → Tagihan Baru**: pilih vendor, isi 2 rincian beban dengan PPN 11% → simpan → ajukan →
    setujui → **Posting** → cek jurnal **D beban + D PPN Masukan / K Utang Usaha**.
20. Pada tagihan yang sudah posted, catat **pembayaran sebagian** dengan potongan PPh → pastikan status
    menjadi `partial`, `sisa` berkurang, dan jurnal **D Utang Usaha / K Bank + K Utang PPh** terbentuk;
    lunasi sisanya → status `paid`.
21. **Piutang Usaha → Invoice Baru**: pilih pelanggan (dan kontrak bila ada), isi rincian pendapatan + PPN →
    posting → jurnal **D Piutang Usaha / K Pendapatan + K PPN Keluaran**; lalu catat **penerimaan** dengan
    PPh dipotong pelanggan.
22. **Pengakuan Pendapatan → Baru**: pilih kontrak, metode persentase penyelesaian 25% → nilai terisi dari
    25% nilai kontrak → posting → jurnal **D Piutang Belum Ditagih / K Pendapatan Usaha**; coba nilai yang
    melebihi nilai kontrak → ditolak.
23. Buka daftar **Hutang Usaha** dan **Piutang Usaha** → kartu aging 5 bucket terisi dan totalnya sama dengan
    jumlah sisa dokumen posted/partial.

**Tahap 5 — anggaran & approval**

24. **RKAP & Versi → RKAP Baru**: pilih tahun, isi 2 baris (satu akun pendapatan, satu akun beban) → simpan →
    pastikan total 12 bulan pada detail sama dengan pagu tahunan dan total dihitung otomatis di form.
25. Ajukan RKAP → setujui → **Aktifkan** → buka **Monitoring Realisasi** → serapan, sisa pagu, dan status
    `aman/waspada/over` muncul; versi aktif lain (bila ada) otomatis berstatus `arsip`.
26. Dari RKAP aktif, klik **Buat Revisi** → draft versi +1 dengan salinan baris dan nomor `RKAP-REV-…`.
27. **Komitmen PO**: catat PO baru (vendor, akun beban, nilai) → pagu tersedia pada monitoring berkurang;
    ubah status ke `closed` → pagu kembali.
28. **Approval Center**: dari salah satu modul klik *Ajukan* → permintaan muncul di antrean dengan SLA;
    setujui level 1 → level naik; selesaikan level terakhir → dokumen modul ikut berstatus disetujui.
    Coba **delegasikan** ke pejabat lain lalu proses dari akun penerima delegasi, dan tolak permintaan
    dengan catatan → riwayat aksi tercatat.

**Tahap 6 — aset tetap & pajak**

29. **Aset Tetap → Aset Baru**: pilih kategori (umur & metode terisi otomatis), isi harga perolehan →
    simpan → aset berstatus draft dengan kode `AST/…`; klik **Aktifkan** dengan rekening bank →
    jurnal **D akun aset / K bank** terbentuk dan kartu mutasi perolehan tertaut ke jurnal.
30. **Aset Tetap → Penyusutan**: pilih periode terbuka → periksa rencana per aset (yang dilewati menampilkan
    alasan) → **Simpan Draft** → **Posting Jurnal** → satu jurnal `DEP` terbentuk per kategori dan nilai buku
    aset berkurang; coba posting ulang → ditolak karena draft sudah habis.
31. Dari detail aset, catat **mutasi lokasi**, lalu **penjualan** di atas nilai buku → jurnal memuat laba
    `4-2200`; coba harga di bawah nilai buku → jurnal memuat rugi `5-3900`. **Penghapusan** memakai alasan wajib.
32. **Perpajakan → Dokumen Baru**: pilih jenis PPN (tarif 11% otomatis) dan isi DPP → nilai pajak terhitung
    otomatis → simpan. Buat **SSP** lalu **Setor** dengan rekening bank → status `posted` dan jurnal pajak
    terbentuk. Buka **Rekap SPT** → **Hitung Rekap** PPN → angka kurang bayar = keluaran − masukan, ubah status
    menjadi `final`/`dilaporkan`, lalu **Unduh e-Faktur CSV** dan periksa isinya.

**Tahap 7 — pelaporan keuangan & PDF**

33. Buka **Laporan Keuangan** → pilih periode → periksa kartu Total Aset, Pendapatan, Laba Bersih, dan
    Kas & Bank; grafik tren enam bulan serta tabel rasio terisi dari jurnal posted.
34. Buka **Laba Rugi** → bandingkan kolom periode ini dengan pembanding dan Δ%; ubah mode ke
    **s.d. tahun berjalan** → rentang berubah dan angka mengikuti YTD.
35. Buka **Neraca** → pastikan indikator **Neraca seimbang** aktif dan total aset = kewajiban + ekuitas;
    baris laba tahun berjalan muncul di ekuitas.
36. Buka **Arus Kas** → periksa pengelompokan operasi/investasi/pendanaan, kas awal + neto = kas akhir,
    dan status rekonsiliasi; lalu **Perubahan Ekuitas** dan **Laba Rugi Proyek**.
37. Terapkan filter bidang/divisi/proyek → seluruh laporan berubah konsisten; kosongkan filter untuk
    kembali ke angka penuh.
38. Klik **PDF** → file `laporan-*.pdf` terunduh dan dapat dibuka (kop, tabel, catatan, tanda tangan);
    ulangi untuk **Excel** dan **CSV**, lalu buka **Cetak** dan simpan sebagai PDF dari browser.

**Tahap 8 — integrasi & hardening**

39. Buka **Pengadaan & Persediaan → Purchase Order** → buat PO barang + jasa → **Ajukan** → setujui dengan
    akun berpermission `procurement.approve`; periksa **Komitmen PO** pada modul anggaran bertambah.
40. Buka detail PO → **Terima Barang** → isi qty sebagian → status menjadi *Diterima Sebagian*, stok barang
    bertambah pada **Persediaan**, dan kartu stok memuat mutasi masuk.
41. Setelah seluruh qty barang diterima → status PO *Selesai* → **Tarik Tagihan AP** → draft tagihan terbuka;
    coba tarik ulang → ditolak.
42. Buka **Persediaan → Barang Keluar** → isi qty melebihi stok → ditolak; isi qty valid → jurnal
    **D beban / K persediaan** terbentuk dan saldo kartu stok berkurang.
43. Buka **Proyek & Kontrak → Portofolio** → periksa laba/margin/progres proyek → buka **Kontrak & Termin** →
    aktifkan kontrak → **Tagih Termin** → draft invoice AR terbuka dengan `termin_ke` terisi.
44. Buka **Laporan Keuangan → Kinerja Unit Usaha** → periksa kontribusi tiap unit dan filter unit pada
    laporan lain; angka konsolidasi harus sama dengan total laba rugi.

**Tahap 9 — hardening lanjutan & operasional**

45. Aktifkan **Pengaturan → Blokir transaksi bila melebihi pagu**, lalu ajukan jurnal beban yang melewati
    pagu → posting ditolak dengan pesan akun/pagu/realisasi; nonaktifkan kembali untuk melanjutkan.
46. Buka **System Admin → Cadangan Database → Buat Cadangan Sekarang** → file muncul di riwayat →
    unduh dan pastikan isinya berisi `CREATE TABLE` serta `INSERT`; coba akses URL file langsung → ditolak.
47. Jalankan `php bin/backup.php` dari terminal/cron → ringkasan tercetak dan exit code 0; periksa file baru
    pada folder `storage/backups`.
48. Baca `docs/panduan-pengguna.md` dan lakukan uji pemulihan di server staging sebelum go-live.

---

## 9. Catatan Pengembangan

- Branch kerja: `arena/d346e56d-ljufinance`.
- Setiap tahap diselesaikan lalu **berhenti untuk konfirmasi** sebelum tahap berikutnya.
- `projects.md` adalah acuan arsitektur (skema, modul, roadmap) dan diperbarui setiap tahap.
- Tidak ada library pihak ketiga (tanpa Composer, tanpa CDN, tanpa Dompdf).

© PT. Lampung Jasa Utama — penggunaan internal.
