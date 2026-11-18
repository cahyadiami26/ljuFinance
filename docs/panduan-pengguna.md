# Panduan Pengguna LJU Finance

Panduan ringkas untuk pengguna harian. Dokumentasi arsitektur dan skema ada di
[`projects.md`](../projects.md); kebutuhan sistem dan instalasi ada di [`readme.md`](../readme.md).

---

## 1. Peran & tanggung jawab

| Peran | Fokus utama |
|-------|-------------|
| Staff Finance | Kas & bank, uang muka, biaya, hutang/piutang, pengadaan |
| Staff Accounting | Jurnal, buku besar, aset, pajak, laporan, persediaan |
| Kepala Divisi / Bidang | Pengajuan anggaran, persetujuan biaya, monitoring realisasi |
| Kepala Bidang Keuangan | Persetujuan jurnal, tagihan, pengadaan, laporan |
| Direktur Operasional | Persetujuan operasional & pengadaan |
| Direktur Utama | Persetujuan akhir dan pembacaan laporan |
| Auditor | Pembacaan seluruh data dan audit trail (tanpa mengubah transaksi) |
| Super Admin | Pengguna, role, pengaturan, cadangan database |

Hak akses bersifat granular (`modul.aksi`). Bila tombol tidak muncul, hubungi admin —
jangan memakai akun orang lain.

### Menarik data pengguna dari portal SSO

Pengguna baru biasanya muncul otomatis saat pertama kali login. Untuk mengisi seluruh daftar sekaligus:

1. Buka **System Admin → Pengguna & Hak Akses**.
2. Klik **Sinkronkan dari SSO** di kanan atas daftar.
3. Konfirmasi; sistem membaca tabel `users` pada database portal dan memperbarui `app_users`.
4. Ringkasan jumlah pengguna baru, diperbarui, dilewati, dan gagal tampil di atas halaman.

Yang **tidak** diubah oleh sinkronisasi: role aplikasi, bidang/divisi yang sudah diisi manual, dan
riwayat login. Bila muncul pesan database portal tidak dapat diakses, minta admin memeriksa
`sso.db` dan kredensial database pada `config/config.local.php`.

---

## 2. Alur harian yang disarankan

1. **Kas & bank** — catat penerimaan/pengeluaran, unggah bukti, lalu posting.
2. **Pengajuan biaya / uang muka** — ajukan, setujui, cairkan, dan pertanggungjawabkan.
3. **Hutang & piutang** — input tagihan, posting, catat pembayaran/penerimaan.
4. **Pengadaan** — buat PO → ajukan → setujui → terima barang → tarik tagihan AP.
5. **Persediaan** — catat barang keluar untuk proyek/kantor; jurnal beban terbentuk otomatis.
6. **Proyek & kontrak** — perbarui progres, tagih termin, periksa laba/margin proyek.
7. **Laporan keuangan** — cek laba rugi, neraca, arus kas, dan kinerja unit usaha.

---

## 3. Pengadaan & penerimaan barang

1. Buka **Pengadaan & Persediaan → Purchase Order → PO Baru**.
2. Pilih vendor, tanggal, proyek, dan dimensi organisasi; isi baris barang/jasa, qty, harga, PPN.
3. **Simpan PO** (status draft) → **Ajukan Persetujuan**.
4. Pejabat ber-permission `procurement.approve` membuka PO → **Setujui PO**.
   PO otomatis menjadi komitmen anggaran.
5. Buka **Terima Barang** → isi qty yang datang (tidak boleh melebihi sisa) → simpan.
   Stok bertambah dan harga rata-rata barang diperbarui.
6. Setelah seluruh qty barang diterima, status PO menjadi **Selesai**.
7. Klik **Tarik Tagihan AP** → draft tagihan vendor terbuka di modul Hutang.
   Tagihan hanya dapat ditarik satu kali per PO.
8. Lengkapi dan posting tagihan di modul Hutang agar jurnal pembelian terbentuk.

> Baris jasa (non-stok) tidak memiliki qty fisik; nilainya ikut ditagihkan penuh saat tagihan ditarik.

---

## 4. Persediaan

- **Barang baru**: kode, nama, kategori, satuan, akun persediaan, dan akun beban pengeluaran.
- **Barang masuk manual**: menambah stok dengan rata-rata bergerak; tidak membentuk jurnal.
- **Barang keluar**: wajib memiliki akun beban. Sistem membentuk jurnal **D beban / K persediaan**.
  Qty melebihi stok akan ditolak.
- **Kartu stok** menampilkan seluruh mutasi beserta saldo setelah transaksi.
- Barang dengan stok ≤ stok minimum ditandai **Rendah** pada daftar persediaan.

---

## 5. Proyek & kontrak

1. Buat proyek pada **Master Data → Proyek** atau **Portofolio Proyek → Proyek Baru**.
2. Buat kontrak: nomor, judul, proyek, pemberi kerja, nilai, masa berlaku, PPN, dan termin.
   Nilai termin dapat dihitung otomatis dari persentase kontrak.
3. **Aktifkan Kontrak** agar termin dapat ditagih.
4. Pada detail kontrak, klik **Tagih Termin** → draft invoice AR terbentuk dengan `termin_ke`
   dan nilai + PPN sesuai kontrak. Posting invoice di modul Piutang.
5. Pantau laba/margin/progres pada halaman detail proyek. Angka diambil dari jurnal posted
   yang memiliki dimensi proyek.

---

## 6. Anggaran & hard stop

- RKAP disusun per akun dan dimensi, disebar 12 bulan, lalu diajukan → disetujui → **diaktifkan**.
- Monitoring menampilkan pagu, realisasi jurnal posted, komitmen PO, sisa pagu, dan status.
- Bila **Pengaturan → Blokir transaksi bila melebihi pagu** diaktifkan:
  - jurnal otomatis dan posting jurnal manual akan **ditolak** ketika nilai beban melebihi pagu
    akun + divisi pada RKAP aktif;
  - pesan penolakan menyebut akun, pagu, realisasi, dan nilai transaksi;
  - pendapatan, aset, kewajiban, dan ekuitas tidak diperiksa.
- Matikan opsi tersebut hanya dengan persetujuan pimpinan dan catat alasannya pada audit.

---

## 7. Tutup buku & laporan

1. Pastikan seluruh transaksi periode sudah diposting dan rekonsiliasi bank selesai.
2. Buka **Accounting → Tutup Buku**, periksa saldo, lalu jalankan penutupan.
3. Setelah periode ditutup, posting ke periode tersebut ditolak.
4. Gunakan **Laporan Keuangan** untuk laba rugi, neraca, arus kas, perubahan ekuitas,
   laba rugi proyek, dan kinerja unit usaha. Semua laporan dapat dicetak atau diekspor
   ke PDF/Excel/CSV sesuai hak `report.export`.

---

## 8. Cadangan & pemulihan

**Melalui aplikasi** (Super Admin): **System Admin → Cadangan Database → Buat Cadangan Sekarang**.
File dapat diunduh dari tabel riwayat. Cadangan lebih lama dari retensi (default 14 hari)
dihapus otomatis saat cadangan baru dibuat.

**Melalui cron** (disarankan untuk database besar):

```cron
0 1 * * * /usr/bin/php /path/ke/ljuFinance/bin/backup.php >> /path/ke/ljuFinance/storage/logs/backup.log 2>&1
```

**Memulihkan**:

```bash
mysql -u USER -p NAMA_DATABASE < lju-finance-YYYYmmdd-HHMMSS.sql
```

Uji pemulihan di server staging secara berkala. Jangan memulihkan ke produksi tanpa
cadangan terbaru dan tanpa menghentikan aplikasi.

---

## 9. Kewajiban keamanan

- Jangan berbagi akun atau sesi SSO; logout setelah memakai komputer bersama.
- Verifikasi ulang nominal dan akun sebelum menyetujui/memposting.
- Laporkan segera aktivitas mencurigakan pada **Audit Trail**.
- Hapus `install.php` setelah instalasi selesai.
- Simpan file cadangan di lokasi terpisah dari server aplikasi (unduh lalu arsipkan).
