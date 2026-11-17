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
│   ├── js/app.js              # sidebar, modal, konfirmasi, format angka, chart tooltip
│   └── img/                   # fallback logo, placeholder
├── uploads/                   # bukti transaksi (diproteksi dari eksekusi PHP)
├── vendor/dompdf/             # domPDF (manual, tanpa composer)
├── database/
│   ├── schema/                # 01_foundation.sql ... 07_approval.sql
│   └── seed/                  # 01_reference.sql (wajib), 02_demo.sql (opsional)
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

Catatan: persetujuan jurnal pada tahap ini masih di level modul (`journal.approve`). Pada Tahap 5
seluruh approval dimigrasikan ke **Approval Center** (`approval_requests`, delegasi, SLA) tanpa
mengubah status jurnal yang sudah ada.

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

### Tahap 3 — Kas & Bank, Uang Muka, Biaya ⬜
Petty cash, kas masuk/keluar, transfer antar rekening, mutasi rekening, rekonsiliasi bank,
monitoring saldo real-time, cash advance (pengajuan → persetujuan → pencairan → pertanggungjawaban →
pengembalian sisa), expense management (perjalanan dinas, rapat, pemeliharaan, transportasi) berikut
verifikasi bukti dan jurnal otomatis.

### Tahap 4 — Hutang & Piutang ⬜
AP: tagihan supplier, termin, approval pembayaran, pembayaran sebagian/penuh, aging payable.
AR: invoice & contract billing, pengakuan pendapatan, penerimaan, riwayat pelanggan, aging receivable.

### Tahap 5 — Anggaran & Pengendalian ⬜
RKAP (penyusunan, per bidang/divisi/akun/proyek), revisi & persetujuan, monitoring realisasi + peringatan
ambang batas, komitmen PO, Cost Center & Profit Center analysis, **Approval Center** penuh
(delegasi, penggantian pejabat, SLA, notifikasi, riwayat).

### Tahap 6 — Aset Tetap & Perpajakan ⬜
Aset tetap (kode inventaris, perolehan, lokasi, penanggung jawab, penyusutan otomatis garis lurus/saldo
menurun, mutasi, penjualan/penghapusan, nilai buku), pajak (PPN, PPh 21/22/23/4(2), bukti potong,
rekap bulanan, arsip, rekonsiliasi, persiapan e-Faktur/DJP).

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
