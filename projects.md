# LJU Finance & Accounting System

**PT. Lampung Jasa Utama (LJU)** — Finance, Accounting & Budget Control Platform
Target domain: `https://finance.lju.co.id`

Dokumen ini adalah **acuan kerja (single source of truth)** untuk arsitektur, konvensi kode, skema data,
dan urutan pengerjaan (roadmap bertahap). Setiap tahap dikerjakan sampai selesai, diuji, lalu berhenti
menunggu konfirmasi sebelum tahap berikutnya dimulai.

- Versi aplikasi: **1.0.0**
- Status dokumen: **aktif** (diperbarui setiap tahap selesai)
- Bahasa antarmuka: Indonesia
- Basis: PHP 8 native (tanpa framework, tanpa Composer), MySQL 5.7+/MariaDB 10.3+, domPDF (instalasi manual)
- Desain: mengikuti referensi dashboard "LJU Finance Enterprise" (sidebar navy, kartu putih, aksen biru)

---

## 1. Tujuan Sistem

| # | Tujuan | Diwujudkan oleh |
|---|--------|-----------------|
| 1 | Mengelola seluruh transaksi keuangan perusahaan | Jurnal Umum, Kas & Bank, Uang Muka, Biaya, AP/AR |
| 2 | Mengontrol anggaran dan realisasi biaya | RKAP, Budget Control, Cost/Profit Center |
| 3 | Mengelola piutang dan hutang | AR (invoice, penerimaan, aging), AP (tagihan, pembayaran, aging) |
| 4 | Menghasilkan laporan keuangan otomatis | Reporting engine + domPDF, tutup buku |
| 5 | Memudahkan monitoring keuangan oleh Direksi | Executive Dashboard + grafik tren |
| 6 | Rekam jejak untuk audit internal & eksternal | Audit trail immutable, dokumen pendukung, periode terkunci |

---

## 2. Prinsip Arsitektur (tidak boleh dilanggar)

1. **Double entry** — setiap jurnal wajib `SUM(debit) == SUM(kredit)`; divalidasi di server sebelum simpan & posting.
2. **Immutable posted** — jurnal berstatus `posted` tidak boleh diubah/dihapus. Koreksi hanya melalui **jurnal pembalik** (reversal).
3. **Nomor referensi unik** — setiap dokumen memakai nomor dari tabel `doc_sequences` (unique per prefix+periode), tidak boleh diinput manual.
4. **Audit log wajib** — semua create/update/delete/approve/post/reverse tercatat di `audit_logs` (old & new value, IP, user agent).
5. **Atomik** — posting jurnal + update saldo + audit log dibungkus satu `DB::transaction()`.
6. **Semua bermuara ke General Ledger** — kas/bank, AP/AR, biaya, aset, pajak membuat jurnal otomatis dengan `sumber` transaksi.
7. **RKAP sebagai kontrol** — setiap beban dicek terhadap pagu anggaran (peringatan lunak; blokir bila kebijakan `budget_hard_stop` aktif).
8. **Approval terpisah dari logika transaksi** — `approval_flows` (konfigurasi) + `approval_requests` (runtime) bersifat generik untuk semua modul.
9. **Dimensi lengkap** — setiap baris jurnal membawa: periode, bidang, divisi, unit usaha, proyek, cost center, partner, sumber, user.
10. **Data historis abadi** — master data memakai *soft deactivate* (`is_active`), tidak hard delete, agar laporan lama tetap utuh.
11. **Tanpa dependensi eksternal** — CSS/JS ditulis sendiri, ikon berupa inline SVG, tidak ada CDN (aman untuk jaringan terbatas).

---

## 3. Struktur Direktori

```
/
├── index.php                  # front controller (semua request masuk ke sini)
├── install.php                # installer: cek requirement, import skema + seed, tulis config
├── .htaccess                  # rewrite ke index.php + proteksi folder sensitif
├── config/
│   ├── config.php             # konfigurasi aplikasi (DB, SSO, upload, PDF) — versi repo
│   └── config.local.php       # override kredensial (dibuat installer / manual, git-ignored)
├── app/
│   ├── bootstrap.php          # autoload, session SSO, error handler, helper global
│   ├── routes.php             # tabel rute + permission yang dibutuhkan
│   ├── Core/                  # mini-framework (Db, Router, Auth, Rbac, View, Csrf, Audit, dll)
│   ├── Repositories/          # akses data (SQL terpusat, prepared statement)
│   ├── Services/              # logika bisnis (posting, budget, approval, numbering, pdf)
│   ├── Controllers/           # satu controller per modul
│   └── Views/
│       ├── layouts/app.php    # kerangka: sidebar + topbar + konten
│       ├── partials/          # sidebar, topbar, icons, pagination, flash, kpi
│       └── pages/             # halaman per modul
├── assets/
│   ├── css/app.css            # design system (satu file, tanpa framework)
│   ├── js/app.js              # sidebar, konfirmasi, format angka, ⌘K
│   ├── js/journal.js          # editor baris jurnal & indikator keseimbangan
│   ├── js/transaksi.js        # baris dinamis kas/uang muka/biaya/rekonsiliasi/AP/AR
│   └── img/                   # fallback logo, placeholder
├── uploads/                   # bukti transaksi (diproteksi dari eksekusi PHP)
├── vendor/dompdf/             # domPDF (manual, tanpa composer)
├── database/
│   ├── schema/                # 01_foundation.sql ... 07_approval.sql
│   ├── seed/                  # 01_reference.sql (wajib), 02_demo.sql (opsional)
│   └── migrations/            # patch idempotent untuk database yang sudah terpasang
├── storage/                   # log, cache, export sementara
└── projects.md, readme.md
```

---

## 4. Konvensi Kode

| Aspek | Konvensi |
|-------|----------|
| Namespace | `Lju\Core`, `Lju\Controllers`, `Lju\Services`, `Lju\Repositories` (PSR-4 manual autoloader) |
| File | `PascalCase.php` untuk class, `snake_case.php` untuk view |
| Database | tabel `snake_case` plural (`journal_entries`), kolom `snake_case`, FK `{tabel_tunggal}_id` |
| Uang | `DECIMAL(18,2)` — **tidak pernah** float. Helper `rupiah()` untuk tampilan |
| Tanggal | `DATE` di DB, format tampilan `d M Y`, timezone `Asia/Jakarta` |
| Escape | semua output lewat `e()` (htmlspecialchars) |
| Query | 100% prepared statement (`Db::all()`, `Db::one()`, `Db::exec()`) |
| CSRF | semua POST wajib `Csrf::check()` |
| Permission | setiap route mendeklarasikan permission; dicek `Auth::can()` di router |

---

## 5. Autentikasi & Sesi (SSO)

Login & sesi dipegang SSO portal `lju.co.id`; aplikasi hanya *consumer*.

```php
ini_set('session.cookie_domain', '.lju.co.id');
session_name('LJU_APPS');
session_set_cookie_params([
    'lifetime' => 86400, 'path' => '/', 'domain' => '.lju.co.id',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true, 'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) session_start();
```

- Bila `$_SESSION['user_id']` kosong → redirect ke portal SSO
  `https://lju.co.id/dashboard/?target_system=...&redirect_after_login=...`.
- Setelah sesi ada, aplikasi melakukan **sinkronisasi user** ke tabel lokal `app_users`
  (id = `users.id` SSO, nik, nama, jabatan, bidang, divisi, avatar) lalu memuat **role lokal** LJU
  (`app_user_roles`) beserta permission-nya.
- Tabel `users` berada di database lain pada mesin MySQL yang sama → koneksi kedua `sso` (read-only).
- Nomenklatur: `users.division` → **nama_bidang**, `users.department` → **nama_divisi** (di aplikasi).
- Avatar: `https://dms.lju.co.id/uploads/avatars/{avatar}`, fallback inisial nama (warna deterministik dari nama).
- Mode pengembangan (`APP_ENV=local`, `DEV_LOGIN=true`) menyediakan halaman *pilih user* agar UI bisa
  diuji tanpa portal SSO. **Wajib `false` di produksi.**

---

## 6. Hak Akses (RBAC)

Role aplikasi (bukan role SSO — SSO hanya `admin|user|op_arsip`):

| Kode | Nama | Cakupan |
|------|------|---------|
| `super_admin` | Super Administrator | semua konfigurasi & transaksi |
| `dirut` | Direktur Utama | dashboard, laporan, approval level akhir |
| `dirops` | Direktur Operasional | dashboard, laporan, approval operasional |
| `kabid_keuangan` | Kepala Bidang Keuangan | seluruh pengelolaan keuangan, posting |
| `staff_accounting` | Staff Accounting | jurnal, buku besar, laporan |
| `staff_finance` | Staff Finance | kas/bank, AP, AR, biaya |
| `kepala_divisi` | Kepala Divisi | pengajuan & monitoring anggaran divisi |
| `kepala_bidang` | Kepala Bidang | pengajuan & monitoring anggaran bidang |
| `auditor` | Auditor Internal | read-only + audit trail |

Permission granular: `<modul>.<aksi>` (contoh `journal.create`, `ap.approve`, `period.close`,
`audit.view`). Disimpan di `app_role_permissions`. Daftar lengkap ada di `database/seed/01_reference.sql`.
Kas & bank memakai pemisahan `cash.view` / `cash.manage` / `cash.approve` / `cash.post`; database yang
dipasang sebelum Tahap 3 perlu menjalankan `database/migrations/2026-11-17_tahap3_cash_permissions.sql`
agar dua permission terakhir tersedia.

Hutang & piutang memakai `ap.view` / `ap.manage` / `ap.approve` / `ap.pay` dan `ar.view` / `ar.manage` /
`ar.approve`; penerimaan piutang memakai `ar.manage` (belum ada `ar.pay` terpisah). Pengakuan pendapatan
mengikuti permission AR. Database lama perlu menjalankan
`database/migrations/2026-11-17_tahap4_ap_ar.sql` untuk akun `1-1330` (piutang belum ditagih),
`1-1440` (PPh dibayar dimuka), dan prefix `REV`.

Anggaran & Approval Center memakai `budget.view/manage/approve` dan `approval.view/act`. Database yang
dipasang sebelum Tahap 5 perlu menjalankan `database/migrations/2026-11-17_tahap5_budget_approval.sql`
agar permission, setting (`budget_alert_percent`, `budget_hard_stop`, `approval_sla_hours`), alur
`AF-BUD-*`, dan prefix `RKAP` tersedia.

Aset tetap & perpajakan memakai `asset.view/manage` dan `tax.view/manage`. Database yang dipasang
sebelum Tahap 6 perlu menjalankan `database/migrations/2026-11-18_tahap6_asset_tax.sql` agar permission,
setting aset/pajak (`asset_auto_depreciate`, `asset_depreciation_day`, `asset_min_capitalize`,
`tax_ppn_rate`, `tax_filing_due_day`), dan prefix `AST`/`DEP`/`TAX` tersedia.

**Approval engine** mendukung: approve, return-for-revision, reject-with-reason, delegasi,
penggantian pejabat (delegation berlaku per modul & rentang tanggal), riwayat persetujuan,
notifikasi (in-app + antrean email), serta matriks **nominal × unit organisasi × level**.

---

## 7. Skema Database (ringkas)

Total **53 tabel**, dibagi 7 domain file. Semua tabel memakai `engine=InnoDB`, `utf8mb4`.

| Domain | File | Tabel |
|--------|------|-------|
| Fondasi & master | `01_foundation.sql` | `app_settings`, `app_users`, `app_roles`, `app_role_permissions`, `app_user_roles`, `audit_logs`, `doc_sequences`, `notifications`, `attachments`, `ms_bidang`, `ms_divisi`, `ms_business_units`, `ms_cost_centers`, `ms_partners`, `ms_projects`, `ms_contracts`, `ms_contract_terms`, `ms_bank_accounts`, `fiscal_years`, `fiscal_periods` |
| Akuntansi inti | `02_accounting.sql` | `coa_accounts`, `journal_entries`, `journal_lines`, `account_period_balances`, `period_closings` |
| Kas & bank, uang muka, biaya | `03_cash_bank.sql` | `cash_transactions`, `cash_advances`, `cash_advance_items`, `expense_requests`, `expense_items`, `bank_reconciliations`, `bank_reconciliation_items` |
| Hutang & piutang | `04_ap_ar.sql` | `ap_invoices`, `ap_invoice_items`, `ap_payments`, `ar_invoices`, `ar_invoice_items`, `ar_payments`, `revenue_recognitions` |
| Anggaran | `05_budget.sql` | `budgets`, `budget_lines`, `budget_commitments` |
| Aset & pajak | `06_asset_tax.sql` | `asset_categories`, `fixed_assets`, `asset_depreciations`, `asset_mutations`, `tax_types`, `tax_documents`, `tax_reports` |
| Approval | `07_approval.sql` | `approval_flows`, `approval_requests`, `approval_actions`, `approval_delegations` |

**Dimensi transaksi** (kolom berulang di `journal_lines` dan tabel transaksi):
`fiscal_year`, `periode_id`, `bidang_id`, `divisi_id`, `business_unit_id`, `project_id`,
`cost_center_id`, `partner_id`, `sumber`, `created_by`.

**Master data kunci**
- Bagan akun berjenjang (`parent_id`, `level`) dengan `saldo_normal`, `allow_posting`, `is_kas_bank`.
- Bidang → Divisi → Cost Center (3 level organisasi) + Unit Usaha + Proyek.
- Rekening bank/kas/petty cash terhubung ke akun COA (`coa_account_id`) untuk jurnal otomatis.

---

## 8. Alur Transaksi → Jurnal → Laporan

```
  Modul bisnis (kas/bank, uang muka, biaya, AP, AR, aset, pajak)
        │  validasi + cek RKAP + approval
        ▼
  Service::post()  ──[DB transaction]──► journal_entries (posted)
        │                                   └─► journal_lines (debit=kredit, +dimensi)
        │                                   └─► account_period_balances (mutasi & saldo)
        │                                   └─► master ikut (saldo bank, sisa hutang/piutang)
        │                                   └─► audit_logs
        ▼
  General Ledger → Trial Balance → Neraca / Laba Rugi / Arus Kas / Perubahan Ekuitas
                                  + laporan per bidang / divisi / proyek / cost center
```

Sumber jurnal (`journal_entries.sumber`): `manual, kas_bank, uang_muka, biaya, ap, ar, aset, pajak,
penyesuaian, penutup, pembalik, integrasi`.

**Tutup buku**: `period_closings` → jurnal penutup (pendapatan/beban → laba ditahan), set
`account_period_balances` awal periode berikutnya, kunci periode (`status=locked`). Tahun buku
ditutup melalui `fiscal_years.status=closed` + jurnal penutup tahunan.

### Implementasi (Tahap 2 — selesai)

| Komponen | Perilaku |
|----------|----------|
| `JournalService` | normalisasi input, validasi (periode terbuka, akun posting, debit=kredit), simpan draft, ajukan → setujui/tolak → posting, pembalik, hapus draft |
| Posting | satu `Db::transaction`: ubah status → `posted`, isi `posted_by/posted_at`, lalu `rebuildYear()` |
| `account_period_balances` | **cache** saldo per akun per periode (debit positif). `saldo_akhir = saldo_awal + mutasi_debit − mutasi_kredit`. Dihitung ulang penuh per tahun buku — idempotent & aman terhadap backdate |
| Laporan | buku besar, buku besar pembantu, dan neraca saldo **dihitung langsung dari `journal_lines` status `posted`**, sehingga tidak pernah bergantung pada cache yang basi |
| Pembalik | jurnal baru `tipe=pembalik` dengan debit/kredit tertukar, langsung posted; jurnal asal → `status=reversed` + `reversed_by_id` |
| Tutup buku bulanan | ditolak bila masih ada jurnal belum posted; set periode `closed`, hitung ulang saldo (carry-forward otomatis), catat `period_closings` |
| Tutup buku tahunan | jurnal penutup `tipe=penutup`: pendapatan (D) & beban (K) → `3-2100 Laba Tahun Berjalan` → `3-2000 Saldo Laba`; seluruh periode → `locked`; `fiscal_years.status=closed` |
| Bukti transaksi | `attachments` (module=`journal`), disimpan di `uploads/journal/YYYY/MM`, unduhan melalui rute ber-permission (bukan URL publik) |

Catatan: persetujuan jurnal pada tahap ini masih di level modul (`journal.approve`). Sejak Tahap 5
seluruh approval juga tercatat pada **Approval Center** (`approval_requests`, delegasi, SLA) tanpa
mengubah status jurnal yang sudah ada.

### Implementasi (Tahap 3 — selesai)

| Komponen | Perilaku |
|----------|----------|
| `AccountMap` | pemetaan akun COA untuk modul transaksi: rekening bank → `ms_bank_accounts.coa_account_id`, uang muka `1-1410`, PPN masukan dari `tax_types.coa_masukan_id`, utang PPh dari `tax_types.coa_keluaran_id`, dengan fallback pencarian kode akun + cache dalam request |
| `JournalService::catat()` | jalur posting jurnal otomatis untuk modul: validasi periode terbuka & debit = kredit, tanpa nomor manual (memakai `doc_sequences`), aman dipanggil di dalam transaksi modul (`inTransaction()` dideteksi, tidak membuat transaksi bersarang) |
| `CashService` | kas masuk/keluar/transfer: nomor `KM/KK/TRF`, status draft → diajukan → disetujui/ditolak → posted; posting atomik (jurnal + `journal_id` + `posted_at` + saldo rekening), transfer mengisi `coa_lawan_id` dengan COA rekening tujuan karena kolom wajib |
| Jurnal kas | masuk: **D Bank / K akun lawan**; keluar: **D akun lawan / K Bank**; transfer: **D Bank tujuan / K Bank asal** — dimensi bidang/divisi/proyek/cost center ikut tercatat pada baris |
| `AdvanceService` | uang muka `CA`: pengajuan → disetujui (jumlah disetujui boleh ≤ pengajuan) → **cair** (D Uang Muka / K Bank) → rincian pertanggungjawaban → **selesai** (D beban sesuai rincian, K Uang Muka sebesar pencairan, selisih sisa → D Bank bila kembali atau K Bank bila kurang bayar) |
| `ExpenseService` | biaya `EXP`: rincian per akun beban dengan PPN & PPh per baris; total = subtotal + PPN − PPh; **bayar** membuat jurnal D beban + D PPN Masukan / K Utang PPh + K Bank, saldo rekening berkurang |
| `BankReconService` | rekonsiliasi: baris buku diambil otomatis dari `journal_lines` akun rekening yang `posted` pada rentang tanggal, baris rekening koran diisi manual, penandaan cocok, rekap `jumlah_cocok`/`jumlah_selisih`, penyelesaian wajib beralasan bila masih ada selisih |
| UI | daftar + form + detail untuk kas/bank, uang muka, biaya, dan rekonsiliasi; kartu saldo rekening real-time, strip ringkasan, editor baris dinamis (`assets/js/transaksi.js`), timeline proses, dan tautan ke jurnal otomatis |
| Penomoran | `KM` (kas masuk), `KK` (kas keluar), `TRF` (transfer), `CA` (uang muka), `EXP` (biaya) melanjutkan `doc_sequences` yang sudah ada |

Catatan: status `cash_advances` memakai `cair` dan `pertanggungjawaban`; `expense_requests` memakai
`dibayar`. Keduanya sudah tersedia pada enum skema sehingga tidak ada migrasi tambahan.

### Implementasi (Tahap 4 — selesai)

| Komponen | Perilaku |
|----------|----------|
| `ApService` | tagihan vendor `AP`: rincian per akun beban/aset dengan PPN masukan per baris, termin & jatuh tempo otomatis dari `ms_partners.termin_hari`, alur draft → diajukan → disetujui → **posted** (D beban + D PPN Masukan / K Utang Usaha), pembayaran sebagian/penuh `APP` (D Utang Usaha / K Bank + K Utang PPh) yang memperbarui `dibayar`, `sisa`, dan status `partial`/`paid`, serta aging 5 bucket |
| `ArService` | invoice pelanggan `AR`: rincian akun pendapatan + PPN keluaran, tautan kontrak/termin/business unit, posting **D Piutang Usaha / K Pendapatan + K PPN Keluaran**, penerimaan `ARP` (**D Bank + D PPh Dibayar Dimuka / K Piutang Usaha**), aging 5 bucket |
| Penagihan atas pengakuan | bila kontrak sudah memiliki `revenue_recognitions` posted yang belum ditagih, bagian itu dikreditkan ke `1-1330 Piutang Belum Ditagih` (bukan pendapatan) sehingga pendapatan tidak diakui dua kali; sisanya tetap ke akun pendapatan |
| `RevenueService` | pengakuan pendapatan `REV` per kontrak/proyek/periode dengan metode `persentase_penyelesaian` (kumulatif terhadap nilai kontrak), `termin`, dan `kontrak_selesai`; nilai dihitung dari nilai kontrak − yang sudah diakui, total pengakuan ditolak bila melebihi nilai kontrak; posting **D Piutang Belum Ditagih / K Pendapatan Usaha** |
| `AccountMap` | tambahan pemetaan: piutang usaha `1-1310`, piutang belum ditagih `1-1330`, utang usaha `2-1110`, PPN keluaran dari `tax_types.coa_keluaran_id` (fallback `2-1210`), PPh dibayar dimuka `1-1440`, pendapatan usaha `4-1100` |
| UI | daftar + form + detail AP (strip ringkasan, aging, panel pembayaran, timeline), daftar + form + detail AR (aging, panel penerimaan, info kontrak), daftar + form + detail pengakuan pendapatan (progress bar kontrak); total & jatuh tempo form otomatis lewat `assets/js/transaksi.js` |
| Penomoran | `AP`, `APP`, `AR`, `ARP`, dan `REV` melanjutkan `doc_sequences` |

Catatan: status `overdue` dihitung saat tampil (`sisa > 0` dan `tanggal_jatuh_tempo < hari ini`) sehingga
tidak perlu job harian; nilai `overdue` pada data demo tetap didukung. `revenue_recognitions`,
`ap_payments`, dan `ar_payments` tidak memiliki kolom `approved_at`, jadi riwayat persetujuan memakai
`updated_at`/`created_at` pada timeline.

### Implementasi (Tahap 5 — selesai)

| Komponen | Perilaku |
|----------|----------|
| `BudgetService` | RKAP tahunan: header `budgets` + baris `budget_lines` per akun & dimensi (bidang/divisi/unit usaha/cost center/proyek), nomor `RKAP/<tahun>/NN` dari `doc_sequences`, penyebaran pagu tahunan ke 12 bulan (sisa pembulatan ke Desember), validasi kombinasi akun × dimensi tidak boleh ganda, jenis baris otomatis mengikuti tipe COA |
| Alur versi | `draft → submitted → approved → aktif → arsip` plus `rejected`; aktivasi mengarsipkan versi aktif lain pada tahun yang sama; **revisi** membuat versi + 1 (`RKAP-REV-NN/<tahun>`) dengan `parent_id` dan salinan baris (INSERT…SELECT) sebagai draft |
| Monitoring realisasi | realisasi per baris dihitung dari `journal_lines` jurnal **posted** tahun berjalan s.d. bulan terpilih, dialokasikan tanpa perhitungan ganda (baris dengan cost center/proyek spesifik mengambil nilai dimensi persis; baris tanpa dimensi rinci mengambil sisa), komitmen PO mengurangi pagu tersedia; status baris `aman` / `waspada` (≥ `budget_alert_percent`) / `over`, total serapan & sisa pagu, rekap per divisi |
| Komitmen pengadaan | `budget_commitments` (nomor PO unik, vendor, akun beban, proyek/divisi/bidang/cost center, nilai, status `open/sebagian/closed/cancelled`) dikelola dari halaman Komitmen PO; hanya status open/sebagian yang mengikat pagu |
| `ApprovalService` | antrean lintas 10 modul; `daftarkan()` memilih `approval_flows` berdasarkan nominal × bidang/divisi, menetapkan `max_level` dan `deadline` dari `sla_jam` (idempotent per `module + ref_id`); `selesaikanOlehModul()` menutup antrean saat modul menyelesaikan persetujuannya sendiri |
| Keputusan | `putuskan()` mendukung **approve** (naik level / selesai), **reject**, **return**, dan **delegate**; otorisasi level memeriksa approver user/role pada alur, delegasi aktif, atau Super Administrator; keputusan final menyinkronkan status dokumen sumber secara best-effort (`approved_by/approved_at` hanya bila kolomnya ada) sehingga status posted/void tidak berubah |
| Delegasi & notifikasi | delegasi per pejabat, opsional per modul dan rentang tanggal (`approval_delegations`), dapat ditutup manual; notifikasi in-app ke pejabat level 1 saat pengajuan masuk, ke penerima saat didelegasikan, dan ke pengaju saat ada keputusan |
| Cost & profit center | `analisisCostCenter()` mengelompokkan pagu & realisasi beban per `ms_cost_centers` (tipe cost/profit/investasi); `analisisProfitCenter()` membandingkan pendapatan vs beban per unit usaha beserta margin pagu/realisasi |
| Integrasi modul | jurnal, kas/bank, uang muka, pengajuan biaya, tagihan AP, invoice AR, pengakuan pendapatan, dan RKAP memanggil hook Approval Center pada saat diajukan/disetujui/ditolak; kegagalan antrean tidak memblokir transaksi |
| UI | `budget/index` (ringkasan versi & komitmen), `budget/form` (editor baris + total otomatis), `budget/show` (monitoring per akun + matriks 12 bulan + komitmen), `budget/monitoring` (serapan, per divisi, cost/profit center, daftar baris), `budget/commitments` (form PO + ubah status), `approval/index` (KPI SLA, filter modul/status/terlambat), `approval/show` (aksi + riwayat), `approval/delegations` |
| Penomoran | `RKAP` melanjutkan `doc_sequences`; revisi memakai prefix `RKAP-REV` |

### Implementasi (Tahap 6 — selesai)

| Komponen | Perilaku |
|----------|----------|
| `AssetService` | kartu inventaris `fixed_assets` dengan kode otomatis `AST/<tahun>/NNNN`, kategori menentukan umur & metode, umur default dari kategori bila dikosongkan, status `draft → aktif → nonaktif → dijual/dihapus`; aktivasi mencatat jurnal **D akun aset / K bank** (atau **K Utang Usaha**) dan menautkan `journal_id` ke kartu serta mutasi perolehan |
| Penyusutan | empat metode: garis lurus `(harga−residu)/umur`, saldo menurun ganda `(harga−akumulasi)×2/umur`, jumlah angka tahun `dasar×(umur−n+1)/Σ`, unit produksi (fallback garis lurus); penyusutan dimulai **bulan berikutnya** setelah perolehan, berhenti di umur ekonomis atau saat nilai buku = residu; nilai dibulatkan 2 desimal dan dibatasi sisa yang dapat disusutkan |
| Alur penyusutan | `rencanaPenyusutan()` menghitung tanpa menyimpan (beserta alasan bila dilewati), `prosesPenyusutan()` menyimpan draft unik per `asset_id + periode_id`, `postingPenyusutan()` membuat **satu jurnal agregat per periode** dengan baris dikelompokkan per akun beban/akumulasi kategori (`sumber='aset'`, `kode_dokumen='DEP'`) lalu memperbarui `akumulasi_penyusutan` dan `nilai_buku` tiap aset; draft dapat dihapus selama belum diposting |
| Mutasi & pelepasan | `asset_mutations` mencatat perolehan, mutasi lokasi, penjualan, penghapusan, dan revaluasi; penjualan **D Bank + D Akumulasi / K Aset / ± Laba (4-2200) atau Rugi (5-3900)**, penghapusan **D Akumulasi + D Rugi / K Aset**; status aset menjadi `dijual`/`dihapus` dan histori tetap tersimpan |
| `TaxService` | dokumen pajak `faktur_keluaran`, `faktur_masukan`, `bukti_potong`, `ssp`, `lain`; tarif otomatis dari `tax_types`, nilai pajak dihitung dari DPP × tarif bila kosong, periode akuntansi ditentukan dari tanggal, nomor `TAX/<MM>/<YYYY>/NNNN` bila dikosongkan, status `draft → posted → reported` dengan `void` untuk pembatalan |
| Penyetoran & pelaporan | `setor()` (khusus SSP) mencatat jurnal **D akun pajak (`tax_types.coa_keluaran_id` / fallback utang PPh) / K bank** dan mengubah status menjadi `posted`; `lapor()` menandai `reported`, `void()` membatalkan dokumen draft, `hapus()` hanya untuk draft tanpa jurnal |
| Rekap & SPT | `rekap()` mengelompokkan dokumen per tipe dan per jenis pajak (dokumen void diabaikan), `generateReport()` menghitung **SPT PPN** (keluaran − masukan) serta PPh 21/23/4(2)/badan dari bukti potong & SSP, lalu melakukan upsert `tax_reports` per `periode_id + jenis`; status SPT `draft → final → dilaporkan` beserta tanggal lapor dan referensi |
| e-Faktur | `efaktur()` menampilkan faktur keluaran periode terpilih dan `efakturCsv()` mengunduh CSV siap impor (`FK;NPWP;Nama;Tanggal;DPP;Tarif;PPN;Referensi`) |
| UI | `asset/index` (strip nilai perolehan/akumulasi/nilai buku, distribusi per kategori, filter kategori/status), `asset/form` (umur & metode otomatis dari kategori), `asset/show` (KPI, riwayat penyusutan, timeline mutasi, panel aktivasi/mutasi/penjualan/penghapusan), `asset/depreciation` (rencana, draft, posting, hapus draft), `tax/index` (ringkasan PPN & PPh periode), `tax/form`, `tax/show` (setor SSP, lapor, void), `tax/report` (rekap per tipe/jenis, status SPT, data e-Faktur) |
| Penomoran | `AST`, `DEP`, dan `TAX` melanjutkan `doc_sequences` |

Catatan: dokumen pajak dari AP/AR/biaya menautkan `ref_module`/`ref_id` sebagai kunci rekonsiliasi, tetapi
belum ada pencocokan otomatis baris jurnal ↔ dokumen pajak. e-Faktur dihasilkan sebagai CSV, bukan
integrasi langsung ke aplikasi DJP. Penjualan/penghapusan aset belum memakai Approval Center karena
`approval_flows` belum memiliki modul aset.

Catatan: status dokumen modul **tidak diubah** oleh lapisan approval — Approval Center hanya
mencerminkan dan menyinkronkan status yang sama (`submitted/approved/rejected/draft`). Perhitungan
realisasi memakai jurnal posted, bukan cache `account_period_balances`, sehingga konsisten dengan
buku besar. Hard stop anggaran (`budget_hard_stop`) masih bersifat kebijakan: nilainya tersedia di
Pengaturan dan ditampilkan pada halaman monitoring, penerapan blokir posting menyusul di Tahap 8.

---

## 9. Nomor Dokumen

Format: `{PREFIX}/{MM}/{YYYY}/{SEQ}` atau template bebas per prefix (mis. `PO-2025-0891`).
Tabel `doc_sequences` (kolom unik `kode` + `period_key`) di-increment di dalam transaksi DB
(`SELECT ... FOR UPDATE`) agar tidak bentrok saat konkuren.

Prefix awal: `JU` (jurnal umum), `KK` (kas keluar), `KM` (kas masuk), `TRF`, `CA` (uang muka),
`EXP` (biaya), `AP` (tagihan), `APP` (pembayaran hutang), `AR` (invoice), `ARP` (penerimaan),
`AST`, `DEP`, `TAX`, `RKAP`, `REV`.

---

## 10. Design System (mengacu gambar referensi)

| Token | Nilai |
|-------|-------|
| Sidebar | `#0b1728 → #0e1d33` (gradient vertical), teks `#94a3b8`, aktif `#1d4ed8` |
| Background | `#f2f5f9` |
| Kartu | `#ffffff`, border `1px #e5eaf2`, radius `12px`, shadow halus |
| Primary | `#1d4ed8` (hover `#1a44bd`) |
| Sukses / Peringatan / Bahaya | `#16a34a` / `#d97706` / `#dc2626` |
| Heading | `#0f172a`, body `#475569`, muted `#94a3b8` |
| Font | `Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif` |
| Angka | `font-variant-numeric: tabular-nums` |
| Ikon | inline SVG 18–20 px, `stroke-width: 1.6` |
| Densitas | tinggi baris tabel 44–56 px, padding kartu 20–24 px |

Komponen: `card`, `card-header`, `kpi`, `stat-grid`, `progress`, `badge` (soft/outline/solid),
`table.data`, `tabs`, `filterbar`, `btn` (primary/ghost/outline/danger/sm), `form-grid`, `field`,
`empty-state`, `timeline` (audit feed), `pill`.

Responsif: sidebar menjadi drawer < 1024 px; grid KPI 4 → 2 → 1 kolom.

---

## 11. Laporan & PDF

- Semua laporan punya filter (periode, bidang, divisi, cost center, proyek, unit usaha) dan ekspor:
  **PDF (domPDF)**, **Excel (XLSX via SpreadsheetML/HTML table)**, **CSV**, serta **cetak** (CSS print).
- domPDF di `vendor/dompdf/` (tanpa Composer) — `app/Services/PdfService.php` mengecek keberadaan
  `vendor/dompdf/autoload.inc.php`; bila belum ada, ekspor PDF otomatis *fallback* ke halaman cetak
  HTML (tidak pernah error ke user).
- Laporan wajib: Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas, Neraca Saldo, Buku Besar, Jurnal Umum,
  Hutang, Piutang, Aging AR/AP, Anggaran vs Realisasi, Aset Tetap, Rekap Pajak.

---

## 12. Roadmap Pengerjaan

Legenda: ✅ selesai · 🚧 berjalan · ⬜ belum

### Tahap 1 — Fondasi, Master Data & Executive Dashboard ✅
- Struktur repo, `.htaccess`, `install.php`, config & bootstrap, autoloader.
- Mini-framework: `Db` (PDO + transaction), `Router`, `Auth` (SSO + sinkronisasi user), `Rbac`, `View`,
  `Csrf`, `Flash`, `Validator`, `Audit`, `Paginator`, helper global.
- **Seluruh skema database (53 tabel)** + seed referensi (COA 109 akun, 9 role + permission, 5 bidang,
  8 divisi, cost center, unit usaha, vendor/customer, proyek, rekening bank, tarif pajak, periode 2025–2026,
  approval flow) + seed demo (opsional, agar dashboard langsung berisi).
- Design system `assets/css/app.css` + `assets/js/app.js` + layout sidebar/topbar sesuai referensi.
- Halaman: **Dashboard eksekutif** (KPI, realisasi RKAP per divisi, saldo kas/bank, matriks aging AR/AP,
  antrean persetujuan, feed audit), **Master Data** (COA, Bidang, Divisi, Cost Center, Unit Usaha,
  Vendor & Customer, Proyek, Rekening Bank, Periode Akuntansi) dengan CRUD penuh,
  **Pengguna & Hak Akses**, **Audit Trail**, **Pengaturan**, **Profil**.
- Audit log aktif untuk semua aksi master data; penomoran dokumen (`doc_sequences`) siap pakai.
- **Installer web** `install.php` (token, cek kebutuhan sistem, import skema + seed, tulis `config.local.php`),
  `.htaccess` root + proteksi folder internal + `uploads/` non-eksekusi skrip.
- **Pratinjau desain statis** (`preview/*.html`) hasil render nyata view PHP dengan data contoh.
- Catatan penyesuaian: `View::partial()` memakai parameter berprefiks (`$__template`, `$__data`) agar
  variabel view seperti `$data` / `$file` tidak tertutup oleh `extract()`.

### Tahap 2 — Akuntansi Inti (Core Accounting) ✅
- `JournalService`: simpan draft multi-baris (dimensi bidang/divisi/proyek/cost center/vendor), validasi
  debit = kredit, akun wajib `allow_posting`, periode wajib terbuka.
- Alur status: **draft → diajukan → disetujui/ditolak → posted**; hapus & ubah hanya pada draft/ditolak.
- Posting atomik (satu transaksi DB) + pembalik otomatis (`tipe=pembalik`, jurnal asal ditandai `reversed`).
- `account_period_balances` sebagai cache saldo per akun/periode (dihitung ulang per tahun buku).
- **Buku Besar** per akun: saldo awal, mutasi berjalan, saldo akhir, filter dimensi + rekap
  buku besar pembantu per bidang/divisi/proyek.
- **Neraca Saldo**: saldo awal, mutasi, saldo akhir per akun, mode periode/kumulatif, pemeriksaan keseimbangan.
- **Tutup Buku**: bulanan (validasi jurnal belum posted, carry-forward saldo), kunci periode permanen,
  buka kembali periode `closed` dengan alasan, tutup tahunan dengan jurnal penutup otomatis.
- **Bukti transaksi**: unggah/unduh/hapus lampiran per jurnal (validasi tipe & ukuran, unduhan ber-permission).
- Penomoran otomatis `JU/KM/KK/TRF` dari `doc_sequences`.
- Mapping akun transaksi memakai kolom yang sudah ada pada master: `ms_bank_accounts.coa_account_id`,
  `tax_types.coa_keluaran_id/coa_masukan_id`, `asset_categories.coa_aset_id/coa_akumulasi_id/coa_beban_penyusutan_id`.

### Tahap 3 — Kas & Bank, Uang Muka, Biaya ✅
- **Kas & Bank**: kas masuk (`KM`), kas keluar (`KK`), transfer antar rekening (`TRF`) dengan kartu saldo
  rekening real-time, filter status/tipe/rekening, dan ringkasan tahun berjalan.
- Alur persetujuan transaksi: draft → diajukan → disetujui/ditolak → **posted**; posting membuat jurnal
  otomatis dan menyesuaikan `ms_bank_accounts.saldo_berjalan` dalam satu transaksi DB.
- **Uang muka**: pengajuan (`CA`), persetujuan dengan jumlah disetujui, pencairan (D Uang Muka / K Bank),
  rincian pertanggungjawaban per akun beban, penyelesaian dengan pengembalian sisa atau penagihan
  kekurangan dana, tautan ke jurnal pencairan & penyelesaian.
- **Pengajuan biaya** (`EXP`): jenis biaya, rincian multi-baris dengan COA beban, PPN masukan dan potongan
  PPh per baris, persetujuan, pembayaran dengan jurnal D beban + D PPN / K utang PPh + K Bank.
- **Rekonsiliasi bank**: rentang tanggal per rekening, saldo buku dihitung dari jurnal posted, baris
  rekening koran manual, penandaan baris cocok, rekap selisih, dan penyelesaian dengan catatan wajib
  bila masih berselisih.
- Mapping akun terpusat di `AccountMap`; jurnal modul memakai `JournalService::catat()`.

### Tahap 4 — Hutang & Piutang ✅
- **Hutang usaha (AP)**: tagihan vendor `AP` dengan rincian akun beban/aset dan PPN masukan per baris,
  termin/jatuh tempo, alur persetujuan, posting jurnal, pembayaran sebagian/penuh `APP` dengan potongan PPh,
  serta aging hutang 5 bucket.
- **Piutang usaha (AR)**: invoice pelanggan `AR` (opsional terhubung kontrak & termin) dengan rincian akun
  pendapatan dan PPN keluaran, persetujuan, posting jurnal, penerimaan `ARP` termasuk PPh dipotong pelanggan,
  dan aging piutang 5 bucket.
- **Pengakuan pendapatan** `REV`: persentase penyelesaian, termin, atau kontrak selesai, dengan validasi
  kumulatif terhadap nilai kontrak dan jurnal D Piutang Belum Ditagih / K Pendapatan; penagihan atas
  bagian yang sudah diakui mengkredit `1-1330` agar pendapatan tidak ganda.

### Tahap 5 — Anggaran & Pengendalian ✅
- **RKAP**: penyusunan per akun dan dimensi (bidang/divisi/unit usaha/cost center/proyek), penyebaran
  otomatis 12 bulan, pengajuan, persetujuan, aktivasi pagu (versi aktif tunggal per tahun), pengarsipan,
  dan **revisi berversi** (`RKAP-REV-NN/<tahun>`) dengan salinan baris.
- **Monitoring realisasi**: pagu vs realisasi jurnal posted s.d. bulan terpilih vs komitmen PO, sisa pagu,
  persentase serapan, status `aman`/`waspada`/`over` terhadap `budget_alert_percent`, rekap per divisi,
  serta rincian bulanan 12 kolom.
- **Komitmen PO**: pencatatan PO/kontrak pengadaan yang mengikat pagu beserta perubahan statusnya.
- **Approval Center**: antrean seluruh modul dengan SLA per level, aksi setujui/tolak/kembalikan/delegasi,
  otorisasi pejabat per level, notifikasi in-app, riwayat aksi, dan statistik ketepatan waktu.
- **Delegasi & penggantian pejabat**: pelimpahan per modul dan rentang tanggal, dapat ditutup manual.

### Tahap 6 — Aset Tetap & Perpajakan ✅
- **Kartu inventaris**: kode otomatis, kategori dengan umur/metode/akun bawaan, lokasi & penanggung jawab,
  dimensi bidang/divisi/proyek, alur draft → aktif (jurnal perolehan) → nonaktif/dijual/dihapus.
- **Penyusutan**: garis lurus, saldo menurun ganda, jumlah angka tahun, unit produksi (fallback garis lurus);
  dimulai bulan berikutnya, draft per periode, posting jurnal agregat per kategori, pembaruan nilai buku.
- **Mutasi & pelepasan**: mutasi lokasi bertimeline, penjualan dengan laba/rugi otomatis, penghapusan aset.
- **Perpajakan**: faktur keluaran/masukan, bukti potong, SSP, penyetoran pajak, rekap PPN & PPh bulanan,
  SPT per jenis dengan status pelaporan, arsip dokumen, serta ekspor CSV e-Faktur untuk DJP.

### Tahap 7 — Pelaporan Keuangan & PDF ⬜
Reporting engine + domPDF (instalasi manual), seluruh laporan wajib, ekspor XLSX/CSV/cetak,
dashboard Direksi lanjutan (grafik tren pendapatan vs pengeluaran, perbandingan antarperiode).

### Tahap 8 — Integrasi & Hardening ⬜
Project accounting (kontrak, termin, pendapatan diakui, laba/rugi proyek), revenue recognition,
integrasi procurement & inventory, multi business unit reporting, pengujian menyeluruh,
dokumentasi pengguna, hardening keamanan & backup.

---

## 13. Instalasi (ringkas)

1. Upload seluruh file ke `public_html` `finance.lju.co.id` (atau docroot lain), PHP ≥ 8.0.
2. Buat database MySQL, isi kredensial di `config/config.local.php` (contoh tersedia di `config/config.php`)
   atau jalankan `install.php?token=...` untuk membuat file konfigurasi otomatis.
3. Import `database/schema/*.sql` berurutan, lalu `database/seed/01_reference.sql`
   (opsional `02_demo.sql` untuk data contoh).
4. Pastikan folder `uploads/` dan `storage/` writable (chmod 775).
5. Pasang domPDF manual: ekstrak ke `vendor/dompdf/` sehingga ada `vendor/dompdf/autoload.inc.php`.
6. Buka `https://finance.lju.co.id` — akan redirect ke portal SSO.

Detail lengkap ada di `readme.md`.

---

## 14. QA Checklist per Tahap

- [ ] `php -l` bersih untuk semua file (dijalankan otomatis sebelum commit).
- [ ] Semua query memakai prepared statement, tidak ada string SQL yang diinterpolasi input user.
- [ ] Semua form POST memiliki token CSRF valid & permission route dicek.
- [ ] Escape output (`e()`) pada setiap variabel yang dicetak.
- [ ] Uji peran: `super_admin`, `dirut`, `staff_finance`, `auditor` (read-only) — menu & aksi sesuai.
- [ ] Angka uang selalu `DECIMAL` dan diformat `rupiah()`.
- [ ] Aksi destruktif memakai konfirmasi + tercatat di audit log.

**Khusus akuntansi (Tahap 2)**

- [ ] Jurnal tidak seimbang ditolak di server (bukan hanya di klien).
- [ ] Posting tidak mungkin pada periode `closed`/`locked`.
- [ ] Neraca saldo seimbang setelah posting & setelah pembalikan.
- [ ] Jurnal `reversed` tidak dihitung ganda (asal diabaikan, pembalik dihitung).
- [ ] `account_period_balances` dapat dihitung ulang tanpa mengubah angka laporan.
- [ ] Tutup buku bulanan menolak periode yang masih punya jurnal belum posted.
- [ ] Lampiran jurnal tidak dapat diakses tanpa login (bukan URL publik langsung).

**Khusus kas & bank (Tahap 3)**

- [ ] Posting kas/uang muka/biaya membuat jurnal yang **seimbang** (debit = kredit) dan menolak periode tertutup.
- [ ] Saldo rekening hanya berubah saat posting/pencairan/pembayaran, bukan saat draft atau pengajuan.
- [ ] Transfer menolak rekening tujuan yang sama dengan rekening asal dan mencatat lawan ke COA rekening tujuan.
- [ ] Pencairan uang muka hanya mungkin dari status `approved`; penyelesaian hanya setelah ada rincian.
- [ ] Selisih uang muka (sisa/kurang) masuk ke rekening bank, bukan ke akun beban.
- [ ] Pembayaran biaya memisahkan PPN masukan (D) dan potongan PPh (K) dari nilai bank (K).
- [ ] Penyelesaian rekonsiliasi menolak selisih tanpa catatan penjelasan.
- [ ] Penomoran `KM/KK/TRF/CA/EXP` melanjutkan urutan `doc_sequences` yang ada (tanpa duplikat).

**Khusus hutang & piutang (Tahap 4)**

- [ ] Posting tagihan AP menghasilkan **D beban/aset + D PPN Masukan = K Utang Usaha** dan hanya dari status `approved`.
- [ ] Pembayaran AP menolak nilai melebihi `sisa`, memperbarui `dibayar`/`sisa`, dan menutup tagihan (`paid`) saat sisa nol.
- [ ] Pembayaran AP memisahkan potongan PPh (K Utang PPh) dari kas keluar bank.
- [ ] Posting invoice AR menghasilkan **D Piutang Usaha = K Pendapatan + K PPN Keluaran**.
- [ ] Penerimaan AR memisahkan PPh dipotong pelanggan (D PPh Dibayar Dimuka) dari kas masuk dan menolak nilai melebihi sisa.
- [ ] Pengakuan pendapatan menolak total yang melebihi nilai kontrak dan periode tertutup.
- [ ] Invoice atas kontrak yang sudah diakui mengkredit `1-1330` sehingga pendapatan tidak tercatat dua kali.
- [ ] Aging AP/AR hanya menghitung dokumen posted/partial dengan `sisa > 0` dan memakai 5 bucket umur.
- [ ] Penomoran `AP/APP/AR/ARP/REV` melanjutkan urutan `doc_sequences` yang ada (tanpa duplikat).

**Khusus anggaran & approval (Tahap 5)**

- [ ] RKAP menolak baris dengan kombinasi akun + dimensi yang sama dan menolak tahun di luar 2000–2100.
- [ ] Menyimpan RKAP menyebar pagu ke 12 bulan dan total kolom bulanan sama dengan pagu tahunan.
- [ ] Aktivasi satu versi mengarsipkan versi `aktif` lain pada tahun yang sama (hanya satu pagu aktif).
- [ ] Revisi membuat versi + 1, menyimpan `parent_id`, dan menyalin seluruh baris sebagai draft.
- [ ] Monitoring hanya menghitung jurnal **posted** sampai bulan terpilih dan tidak menghitung ganda realisasi per baris.
- [ ] Komitmen `open`/`sebagian` mengurangi pagu tersedia; status `closed`/`cancelled` tidak lagi mengikat.
- [ ] Baris melewati 100% ditandai `over`, dan ≥ `budget_alert_percent` ditandai `waspada`.
- [ ] Pengajuan dokumen modul membuat/memperbarui satu permintaan per `module + ref_id` (tanpa duplikat).
- [ ] Keputusan approval menolak pengguna yang bukan pejabat level tersebut atau penerima delegasi aktif.
- [ ] Keputusan final tidak mengubah status posted/void dokumen sumber; hanya status persetujuan modul.
- [ ] Delegasi hanya berlaku pada rentang tanggal dan modul yang dipilih, dan dapat dinonaktifkan.
- [ ] Setiap aksi approval tercatat pada `approval_actions` beserta catatan dan waktu.

**Khusus aset tetap & perpajakan (Tahap 6)**

- [ ] Kode inventaris otomatis `AST/<tahun>/NNNN` dan kode manual yang duplikat ditolak.
- [ ] Aset draft tidak dapat disusutkan; aktivasi mencatat jurnal **D akun aset / K bank** (atau K utang usaha).
- [ ] Penyusutan dimulai bulan berikutnya setelah perolehan, bukan bulan perolehan.
- [ ] Garis lurus, saldo menurun ganda, dan jumlah angka tahun menghasilkan nilai yang berbeda sesuai rumus.
- [ ] Nilai penyusutan tidak pernah membuat akumulasi melebihi `harga − residu`; tanah (umur 0) dilewati.
- [ ] Draft penyusutan unik per aset + periode; posting hanya untuk draft dan menolak periode tertutup.
- [ ] Posting membuat jurnal **seimbang** (`D beban = K akumulasi`) dan memperbarui nilai buku tiap aset.
- [ ] Penjualan di atas nilai buku menghasilkan **laba** `4-2200`, di bawah nilai buku menghasilkan **rugi** `5-3900`.
- [ ] Penghapusan wajib alasan dan tidak mengubah `journal_id` jurnal lain.
- [ ] Dokumen pajak memakai tarif dari `tax_types` bila tarif kosong dan menghitung nilai pajak dari DPP.
- [ ] Penyetoran SSP mencatat **D utang pajak / K bank** dan hanya dapat dilakukan sekali.
- [ ] Rekap PPN = pajak keluaran − pajak masukan; dokumen `void` tidak dihitung.
- [ ] `tax_reports` melakukan upsert per `periode_id + jenis` (tidak membuat duplikat saat hitung ulang).
- [ ] CSV e-Faktur hanya memuat faktur keluaran periode terpilih dan memakai pemisah titik koma.
