<?php

declare(strict_types=1);

namespace Lju\Controllers;

/**
 * Halaman modul yang dijadwalkan pada tahap berikutnya.
 * Menampilkan ruang lingkup, status roadmap, dan dependensi modul.
 */
final class PlaceholderController extends BaseController
{
    private const STAGES = [
        2 => ['title' => 'Akuntansi Inti', 'theme' => 'Jurnal, buku besar, neraca saldo, tutup buku'],
        3 => ['title' => 'Kas & Bank, Uang Muka & Biaya', 'theme' => 'Arus kas harian dan pertanggungjawaban'],
        4 => ['title' => 'Hutang & Piutang', 'theme' => 'Siklus AP/AR dan aging'],
        5 => ['title' => 'Anggaran & Approval Center', 'theme' => 'Kontrol RKAP dan alur persetujuan'],
        6 => ['title' => 'Aset Tetap & Perpajakan', 'theme' => 'Penyusutan dan kepatuhan pajak'],
        7 => ['title' => 'Pelaporan Keuangan & PDF', 'theme' => 'Laporan standar dan ekspor domPDF'],
    ];

    private const MODULES = [
        'journal' => [
            'title' => 'Jurnal Umum',
            'stage' => 2,
            'route' => 'journal',
            'description' => 'Pencatatan jurnal manual, jurnal penyesuaian, akrual, dan pembalik dengan validasi debit = kredit.',
            'features' => [
                'Input jurnal manual dengan nomor otomatis (JU/MM/YYYY/0001)',
                'Jurnal penyesuaian, akrual, dan jurnal pembalik',
                'Upload bukti transaksi dan lampiran pendukung',
                'Alur draft → pengajuan → persetujuan → posting',
                'Pemeriksaan keseimbangan debit & kredit sebelum posting',
                'Pembatalan hanya melalui jurnal pembalik (immutable)',
            ],
        ],
        'ledger' => [
            'title' => 'Buku Besar',
            'stage' => 2,
            'route' => 'ledger',
            'description' => 'Buku besar per akun dan buku besar pembantu dengan filter dimensi lengkap.',
            'features' => [
                'Buku besar per akun dengan saldo awal, mutasi, dan saldo akhir',
                'Buku besar pembantu per vendor, pelanggan, dan proyek',
                'Filter periode, bidang, divisi, cost center, dan proyek',
                'Drill-down dari saldo ke jurnal sumber',
            ],
        ],
        'trial-balance' => [
            'title' => 'Neraca Saldo',
            'stage' => 2,
            'route' => 'trial-balance',
            'description' => 'Trial balance periode berjalan beserta pemeriksaan keseimbangan.',
            'features' => [
                'Neraca saldo per periode atau kumulatif tahun buku',
                'Deteksi ketidakseimbangan debit & kredit',
                'Ekspor PDF, Excel, dan CSV',
            ],
        ],
        'closing' => [
            'title' => 'Tutup Buku',
            'stage' => 2,
            'route' => 'closing',
            'description' => 'Penutupan periode bulanan/tahunan, jurnal penutup, dan penguncian periode.',
            'features' => [
                'Proses tutup buku bulanan dan tahunan',
                'Jurnal penutup otomatis (pendapatan/beban → saldo laba)',
                'Pemindahan saldo awal ke periode berikutnya',
                'Penguncian periode yang sudah ditutup',
            ],
        ],
        'cash' => [
            'title' => 'Cash & Bank',
            'stage' => 3,
            'route' => 'cash',
            'description' => 'Pengelolaan kas kecil, kas masuk/keluar, transfer antar rekening, dan rekonsiliasi bank.',
            'features' => [
                'Pengelolaan petty cash dan rekening bank perusahaan',
                'Penerimaan & pengeluaran kas/bank dengan jurnal otomatis',
                'Transfer antar rekening dan mutasi rekening',
                'Rekonsiliasi bank (matching mutasi vs buku)',
                'Monitoring saldo kas & bank real-time',
            ],
        ],
        'cash/advance' => [
            'title' => 'Uang Muka (Cash Advance)',
            'stage' => 3,
            'route' => 'cash/advance',
            'description' => 'Siklus uang muka kegiatan sampai pertanggungjawaban dan pengembalian sisa.',
            'features' => [
                'Pengajuan, persetujuan, dan pencairan uang muka',
                'Pertanggungjawaban penggunaan dana + upload bukti',
                'Pengembalian sisa uang muka',
                'Monitoring uang muka yang belum dipertanggungjawabkan',
            ],
        ],
        'expense' => [
            'title' => 'Expense Management',
            'stage' => 3,
            'route' => 'expense',
            'description' => 'Pengajuan biaya operasional, perjalanan dinas, rapat, dan pemeliharaan.',
            'features' => [
                'Kategori biaya: operasional, perjalanan dinas, rapat, pemeliharaan, transportasi',
                'Verifikasi bukti transaksi dan perhitungan PPN/PPh',
                'Persetujuan pembayaran berjenjang',
                'Kontrol terhadap pagu anggaran divisi',
            ],
        ],
        'ap' => [
            'title' => 'Account Payable',
            'stage' => 4,
            'route' => 'ap',
            'description' => 'Tagihan supplier, termin pembayaran, pembayaran sebagian/penuh, dan aging hutang.',
            'features' => [
                'Pencatatan tagihan supplier dengan nomor invoice & jatuh tempo',
                'Termin pembayaran dan persetujuan pembayaran',
                'Pembayaran sebagian atau penuh dengan potongan PPh',
                'Monitoring hutang jatuh tempo',
                'Laporan umur hutang (aging payable)',
            ],
        ],
        'ar' => [
            'title' => 'Account Receivable',
            'stage' => 4,
            'route' => 'ar',
            'description' => 'Invoice pelanggan, pengakuan pendapatan, penerimaan pembayaran, dan aging piutang.',
            'features' => [
                'Pembuatan invoice & contract billing per termin',
                'Pengakuan pendapatan dan monitoring tagihan pelanggan',
                'Penerimaan pembayaran sebagian atau penuh',
                'Riwayat pembayaran pelanggan',
                'Laporan umur piutang (aging receivable)',
            ],
        ],
        'budget' => [
            'title' => 'RKAP & Budget Control',
            'stage' => 5,
            'route' => 'budget',
            'description' => 'Penyusunan RKAP, revisi, persetujuan, dan monitoring realisasi per dimensi.',
            'features' => [
                'Anggaran per bidang, divisi, akun biaya, dan proyek',
                'Pengajuan revisi dan persetujuan berjenjang',
                'Monitoring anggaran vs realisasi dengan peringatan ambang batas',
                'Analisis cost center & profit center',
            ],
        ],
        'approval' => [
            'title' => 'Approval Center',
            'stage' => 5,
            'route' => 'approval',
            'description' => 'Pusat persetujuan seluruh dokumen keuangan dengan konfigurasi fleksibel.',
            'features' => [
                'Approve, kembalikan untuk revisi, atau tolak dengan alasan',
                'Delegasi approver dan penggantian pejabat',
                'Riwayat persetujuan dan SLA per level',
                'Matriks nominal × unit organisasi × level',
                'Notifikasi in-app dan email',
            ],
        ],
        'asset' => [
            'title' => 'Fixed Asset',
            'stage' => 6,
            'route' => 'asset',
            'description' => 'Database aset tetap, penyusutan otomatis, mutasi, dan nilai buku.',
            'features' => [
                'Kode inventaris, harga perolehan, lokasi, penanggung jawab',
                'Penyusutan otomatis (garis lurus, saldo menurun, unit produksi)',
                'Mutasi aset, penjualan, dan penghapusan',
                'Laporan nilai buku aset',
            ],
        ],
        'tax' => [
            'title' => 'Tax Management',
            'stage' => 6,
            'route' => 'tax',
            'description' => 'Pencatatan PPN & PPh, bukti potong, rekapitulasi bulanan, dan rekonsiliasi pajak.',
            'features' => [
                'Pengaturan tarif dan jenis pajak (PPN, PPh 21/22/23/4(2))',
                'Pencatatan bukti potong dan faktur pajak',
                'Rekapitulasi pajak bulanan',
                'Arsip dokumen dan rekonsiliasi dengan akuntansi',
                'Persiapan pertukaran data DJP (e-Faktur/e-Bupot)',
            ],
        ],
        'report' => [
            'title' => 'Financial Reports',
            'stage' => 7,
            'route' => 'report',
            'description' => 'Laporan keuangan standar otomatis dengan ekspor PDF (domPDF), Excel, dan CSV.',
            'features' => [
                'Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas',
                'Neraca Saldo, Buku Besar, Jurnal Umum',
                'Laporan Hutang, Piutang, Aging AR/AP',
                'Laporan Anggaran vs Realisasi dan Aset Tetap',
                'Filter per bidang, divisi, unit usaha, proyek, dan cost center',
            ],
        ],
    ];

    public function show(): void
    {
        $path = \Lju\Core\Router::resolvePath();

        $module = self::MODULES[$path] ?? null;
        if ($module === null) {
            // fallback berdasarkan segmen pertama (mis. cash/advance)
            $segments = explode('/', $path);
            $key = isset($segments[1]) ? $segments[0] . '/' . $segments[1] : $segments[0];
            $module = self::MODULES[$key] ?? null;
        }

        if ($module === null) {
            \Lju\Core\App::abort(404, 'Modul tidak ditemukan.');
        }

        $stage = self::STAGES[(int) $module['stage']] ?? ['title' => 'Tahap berikutnya', 'theme' => ''];

        $this->page('pages/soon', [
            'pageTitle'    => (string) $module['title'],
            'pageSubtitle' => (string) $module['description'],
            'breadcrumbs'  => [
                ['label' => 'Modul', 'url' => null],
                ['label' => (string) $module['title'], 'url' => null],
            ],
            'module' => $module,
            'stage'  => $stage,
        ]);
    }
}
