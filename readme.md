# LJU Finance & Accounting System

Sistem informasi keuangan dan akuntansi **PT. Lampung Jasa Utama** — dibangun dengan **PHP native + MySQL**
tanpa framework dan tanpa Composer (domPDF dipasang manual), dirancang untuk dijalankan di
`https://finance.lju.co.id`.

Repositori ini sedang dibangun **bertahap (8 tahap)**. Status rinci tiap tahap ada di
[`projects.md`](projects.md).

| Tahap | Tema | Status |
|-------|------|--------|
| 1 | Fondasi, Master Data & Executive Dashboard | ✅ Selesai |
| 2 | Akuntansi Inti (jurnal, posting, tutup buku) | ✅ Selesai |
| 3 | Kas & Bank, Uang Muka, Biaya | ⬜ Belum |
| 4 | Hutang & Piutang | ⬜ Belum |
| 5 | Anggaran & Approval Center | ⬜ Belum |
| 6 | Aset Tetap & Perpajakan | ⬜ Belum |
| 7 | Pelaporan Keuangan & PDF | ⬜ Belum |
| 8 | Integrasi & Hardening | ⬜ Belum |

> **Pratinjau desain statis:** folder `preview/` berisi HTML hasil render nyata dari view PHP
> (layout, partial, dan CSS yang sama) dengan data contoh: `dashboard`, `login`, `journal-index`,
> `journal-form`, `journal-show`, `ledger`, `trial-balance`, `closing`. Berguna untuk meninjau tampilan
> tanpa server PHP/MySQL.

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
| domPDF | dipasang manual di `vendor/dompdf/` (dipakai Tahap 7) |

---

## 3. Instalasi

1. **Unggah** seluruh repositori ke document root (mis. `public_html`).
2. **Unduh domPDF** dari <https://github.com/dompdf/dompdf/releases>, ekstrak sehingga menjadi:
   `vendor/dompdf/` (berisi `autoload.inc.php`, `src/`, `lib/`). Folder `vendor/` tidak di-commit.
3. **Siapkan database** secara manual (opsional — installer bisa membuatnya):

   ```sql
   CREATE DATABASE lju_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'lju_app'@'localhost' IDENTIFIED BY 'kata-sandi-kuat';
   GRANT ALL PRIVILEGES ON lju_finance.* TO 'lju_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

   Alternatif manual: import berurutan `database/schema/01_foundation.sql` … `07_*.sql`,
   lalu `database/seed/01_reference.sql` dan (opsional) `database/seed/02_demo.sql`.

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
| `upload.*`, `pdf.*` | Batas ukuran/tipe lampiran dan pengaturan domPDF |

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
│   ├── Services/              # Period, Numbering, Dashboard, Journal, Ledger, Closing
│   ├── Controllers/           # Base, Master, Coa, Dashboard, Period, User, Role, Audit, Setting, Profile, Auth,
│   │                          # Journal, Ledger, TrialBalance, Closing, Placeholder
│   └── Views/
│       ├── layouts/           # app.php (shell), blank.php (auth/error)
│       ├── partials/          # sidebar, topbar, flash, dashboard_head
│       └── pages/             # dashboard, master/*, coa/*, period, user, role, audit, settings, profile,
│                              # journal/{index,form,show}, ledger, trial-balance, closing, login, soon, error
├── assets/
│   ├── css/app.css            # design system (token, layout, komponen, editor jurnal)
│   ├── js/app.js              # sidebar, flash, konfirmasi, format uang, ⌘K
│   └── js/journal.js          # editor baris jurnal, total & indikator keseimbangan
├── database/
│   ├── schema/                # 53 tabel, 7 file
│   └── seed/                  # 01_reference.sql, 02_demo.sql
├── preview/                   # HTML statis pratinjau desain
├── storage/                   # log & ekspor (tidak di-commit)
├── uploads/                   # lampiran (tidak di-commit)
└── vendor/                    # domPDF manual (tidak di-commit)
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
  (prefix `JU, KK, KM, TRF, CA, EXP, AP, APP, AR, ARP, AST, DEP, TAX, RKAP`).
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

---

## 9. Catatan Pengembangan

- Branch kerja: `arena/d346e56d-ljufinance`.
- Setiap tahap diselesaikan lalu **berhenti untuk konfirmasi** sebelum tahap berikutnya.
- `projects.md` adalah acuan arsitektur (skema, modul, roadmap) dan diperbarui setiap tahap.
- Library pihak ketiga selain domPDF tidak digunakan (tanpa Composer, tanpa CDN).

© PT. Lampung Jasa Utama — penggunaan internal.
