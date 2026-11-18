<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;
use Lju\Core\Pdf;

/**
 * Mesin laporan keuangan: laba rugi, neraca, arus kas, perubahan ekuitas,
 * laba rugi proyek, rasio, serta tren untuk dashboard direksi.
 *
 * Semua angka dihitung dari `journal_lines` berstatus posted sehingga
 * konsisten dengan buku besar dan neraca saldo. Baris laporan memakai format
 * seragam agar dapat dirender ke HTML, PDF, CSV, dan XLSX.
 */
final class ReportService
{
    public const JENIS = [
        'laba-rugi'     => 'Laporan Laba Rugi',
        'neraca'        => 'Neraca',
        'arus-kas'      => 'Laporan Arus Kas',
        'ekuitas'       => 'Laporan Perubahan Ekuitas',
        'proyek'        => 'Laba Rugi Proyek',
        'unit-usaha'    => 'Kinerja Unit Usaha',
    ];

    public const KATEGORI = [
        'aset_lancar'         => 'Aset Lancar',
        'aset_tetap'          => 'Aset Tetap',
        'aset_lain'           => 'Aset Lain-lain',
        'kewajiban_lancar'    => 'Kewajiban Jangka Pendek',
        'kewajiban_panjang'   => 'Kewajiban Jangka Panjang',
        'ekuitas'             => 'Ekuitas',
        'pendapatan_usaha'    => 'Pendapatan Usaha',
        'pendapatan_lain'     => 'Pendapatan Lain-lain',
        'harga_pokok'         => 'Harga Pokok',
        'beban_operasional'   => 'Beban Operasional',
        'beban_lain'          => 'Beban Lain-lain',
        'pajak'               => 'Pajak',
    ];

    public const ARUS_KAS = [
        'operasi'    => 'Arus Kas dari Aktivitas Operasi',
        'investasi'  => 'Arus Kas dari Aktivitas Investasi',
        'pendanaan'  => 'Arus Kas dari Aktivitas Pendanaan',
    ];

    /* ------------------------------------------------------------------ util */

    public static function uang(mixed $value): string
    {
        return Pdf::money($value);
    }

    /** @return array<string,mixed> */
    public static function periode(int $periodeId = 0): array
    {
        $periode = null;
        if ($periodeId > 0) {
            $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        }
        if ($periode === null) {
            $periode = PeriodService::current();
        }

        return $periode;
    }

    /**
     * Rentang tanggal laporan beserta pembandingnya.
     *
     * @return array{from:string,to:string,prev_from:string,prev_to:string,label:string,prev_label:string}
     */
    public static function rentang(array $periode, string $mode = 'bulan'): array
    {
        $to = (string) ($periode['tanggal_selesai'] ?? date('Y-m-t'));
        $mode = in_array($mode, ['bulan', 'ytd'], true) ? $mode : 'bulan';

        if ($mode === 'ytd') {
            $tahun = (int) ($periode['tahun'] ?? date('Y', strtotime($to)));
            $from = sprintf('%04d-01-01', $tahun);
            $prevFrom = sprintf('%04d-01-01', $tahun - 1);
            $prevTo = sprintf('%04d-%s', $tahun - 1, date('m-d', strtotime($to)));
            $label = 'Januari s.d. ' . date('d M Y', strtotime($to));
            $prevLabel = 'Januari s.d. ' . date('d M Y', strtotime($prevTo));
        } else {
            $from = (string) ($periode['tanggal_mulai'] ?? date('Y-m-01', strtotime($to)));
            $prevTo = date('Y-m-t', strtotime($from . ' -1 day'));
            $prevFrom = date('Y-m-01', strtotime($prevTo));
            $label = date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
            $prevLabel = date('d M Y', strtotime($prevFrom)) . ' – ' . date('d M Y', strtotime($prevTo));
        }

        return [
            'from'       => $from,
            'to'         => $to,
            'prev_from'  => $prevFrom,
            'prev_to'    => $prevTo,
            'label'      => $label,
            'prev_label' => $prevLabel,
            'mode'       => $mode,
        ];
    }

    /** @return array{0:string,1:array<int,int|string>} */
    private static function dimensi(array $filter): array
    {
        $sql = '';
        $params = [];
        foreach (['bidang_id', 'divisi_id', 'business_unit_id', 'project_id'] as $key) {
            $value = (int) ($filter[$key] ?? 0);
            if ($value > 0) {
                $sql .= ' AND jl.' . $key . ' = ?';
                $params[] = $value;
            }
        }
        return [$sql, $params];
    }

    /**
     * Saldo mutasi per akun pada rentang tanggal.
     *
     * @return array<int,array<string,mixed>> kode => baris
     */
    private static function mutasiAkun(string $from, string $to, array $filter = []): array
    {
        [$dimensi, $params] = self::dimensi($filter);

        $rows = Db::all(
            "SELECT c.id, c.kode, c.nama, c.tipe, c.kategori, c.arus_kas, c.saldo_normal, c.is_kas_bank,
                    COALESCE(SUM(jl.debit), 0) AS debit,
                    COALESCE(SUM(jl.kredit), 0) AS kredit
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal BETWEEN ? AND ?" . $dimensi . "
             GROUP BY c.id, c.kode, c.nama, c.tipe, c.kategori, c.arus_kas, c.saldo_normal, c.is_kas_bank
             ORDER BY c.kode",
            array_merge([$from, $to], $params)
        );

        $map = [];
        foreach ($rows as $row) {
            $row['saldo'] = (float) $row['debit'] - (float) $row['kredit'];
            $map[(string) $row['kode']] = $row;
        }
        return $map;
    }

    /**
     * Saldo kumulatif (sejak awal pembukuan) per akun sampai tanggal tertentu.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function saldoAkun(string $to, array $filter = []): array
    {
        [$dimensi, $params] = self::dimensi($filter);

        $rows = Db::all(
            "SELECT c.id, c.kode, c.nama, c.tipe, c.kategori, c.arus_kas, c.saldo_normal, c.is_kas_bank,
                    COALESCE(SUM(jl.debit), 0) AS debit,
                    COALESCE(SUM(jl.kredit), 0) AS kredit
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal <= ?" . $dimensi . "
             GROUP BY c.id, c.kode, c.nama, c.tipe, c.kategori, c.arus_kas, c.saldo_normal, c.is_kas_bank
             ORDER BY c.kode",
            array_merge([$to], $params)
        );

        $map = [];
        foreach ($rows as $row) {
            $row['saldo'] = (float) $row['debit'] - (float) $row['kredit'];
            $map[(string) $row['kode']] = $row;
        }
        return $map;
    }

    /** Nilai tampilan akun menurut tipe: pendapatan/kewajiban/ekuitas dibalik. */
    private static function nilaiAkun(string $tipe, float $saldo): float
    {
        return in_array($tipe, ['pendapatan', 'kewajiban', 'ekuitas'], true) ? -$saldo : $saldo;
    }

    private static function persen(?float $current, ?float $previous): ?float
    {
        if ($previous === null || abs($previous) < 0.005) {
            return null;
        }
        return (($current ?? 0.0) - $previous) / abs($previous) * 100;
    }

    /**
     * Baris awal laporan.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function baris(string $tipe, string $label, array $nilai = [], array $extra = []): array
    {
        return array_merge([
            'tipe'    => $tipe,
            'label'   => $label,
            'nilai'   => array_map(static fn ($v) => round((float) $v, 2), $nilai),
            'persen'  => null,
            'kode'    => '',
            'level'   => 0,
        ], $extra);
    }

    /**
     * Susun dataset laporan.
     *
     * @param array<string,mixed> $filter bidang_id, divisi_id, project_id
     * @return array<string,mixed>
     */
    public static function laporan(string $jenis, int $periodeId = 0, string $mode = 'bulan', array $filter = []): array
    {
        $jenis = array_key_exists($jenis, self::JENIS) ? $jenis : 'laba-rugi';
        $periode = self::periode($periodeId);
        $rentang = self::rentang($periode, $mode);

        if ($jenis === 'unit-usaha') {
            return self::unitUsaha($periodeId, $mode, $filter);
        }

        return match ($jenis) {
            'neraca'   => self::neraca($periode, $rentang, $filter),
            'arus-kas' => self::arusKas($periode, $rentang, $filter),
            'ekuitas'  => self::ekuitas($periode, $rentang, $filter),
            'proyek'   => self::proyek($periode, $rentang, $filter),
            default    => self::labaRugi($periode, $rentang, $filter),
        };
    }

    /** @return array<string,mixed> kerangka dataset */
    private static function dataset(string $jenis, array $periode, array $rentang, array $kolom, array $baris, array $kpi, array $catatan = [], array $extra = []): array
    {
        return array_merge([
            'jenis'    => $jenis,
            'judul'    => self::JENIS[$jenis] ?? 'Laporan',
            'periode'  => $periode,
            'rentang'  => $rentang,
            'kolom'    => $kolom,
            'baris'    => $baris,
            'kpi'      => $kpi,
            'catatan'  => $catatan,
        ], $extra);
    }

    /* ------------------------------------------------------------ LABA RUGI */

    /** @return array<string,mixed> */
    public static function labaRugi(array $periode, array $rentang, array $filter = []): array
    {
        $current = self::mutasiAkun($rentang['from'], $rentang['to'], $filter);
        $previous = self::mutasiAkun($rentang['prev_from'], $rentang['prev_to'], $filter);

        $kategoriUrut = [
            'pendapatan_usaha', 'pendapatan_lain',
            'harga_pokok', 'beban_operasional', 'beban_lain', 'pajak',
        ];

        $total = [];
        foreach ($kategoriUrut as $kategori) {
            $total[$kategori] = [0.0, 0.0];
        }

        $rows = [];
        $bagianPendapatan = ['pendapatan_usaha', 'pendapatan_lain'];

        foreach ($bagianPendapatan as $kategori) {
            $rows[] = self::baris('section', strtoupper(self::KATEGORI[$kategori]));
            $ada = false;
            foreach ($current + $previous as $kode => $akun) {
                $akun = $current[$kode] ?? $previous[$kode];
                if ((string) $akun['tipe'] !== 'pendapatan' || (string) $akun['kategori'] !== $kategori) {
                    continue;
                }
                $cur = self::nilaiAkun('pendapatan', (float) ($current[$kode]['saldo'] ?? 0.0));
                $prev = self::nilaiAkun('pendapatan', (float) ($previous[$kode]['saldo'] ?? 0.0));
                if (abs($cur) < 0.005 && abs($prev) < 0.005) {
                    continue;
                }
                $ada = true;
                $total[$kategori][0] += $cur;
                $total[$kategori][1] += $prev;
                $rows[] = self::baris('data', (string) $akun['nama'], [$cur, $prev], [
                    'kode'   => (string) $akun['kode'],
                    'level'  => 1,
                    'persen' => self::persen($cur, $prev),
                ]);
            }
            if (!$ada) {
                $rows[] = self::baris('data', 'Belum ada transaksi pada periode ini', [0, 0], ['level' => 1]);
            }
            $rows[] = self::baris('subtotal', 'Total ' . self::KATEGORI[$kategori], $total[$kategori]);
        }

        $totalPendapatan = [
            $total['pendapatan_usaha'][0] + $total['pendapatan_lain'][0],
            $total['pendapatan_usaha'][1] + $total['pendapatan_lain'][1],
        ];
        $rows[] = self::baris('total', 'TOTAL PENDAPATAN', $totalPendapatan);
        $rows[] = self::baris('spacer', '');

        foreach (['harga_pokok', 'beban_operasional', 'beban_lain', 'pajak'] as $kategori) {
            $rows[] = self::baris('section', strtoupper(self::KATEGORI[$kategori]));
            $ada = false;
            foreach ($current + $previous as $kode => $akun) {
                $akun = $current[$kode] ?? $previous[$kode];
                if ((string) $akun['tipe'] !== 'beban' || (string) $akun['kategori'] !== $kategori) {
                    continue;
                }
                $cur = self::nilaiAkun('beban', (float) ($current[$kode]['saldo'] ?? 0.0));
                $prev = self::nilaiAkun('beban', (float) ($previous[$kode]['saldo'] ?? 0.0));
                if (abs($cur) < 0.005 && abs($prev) < 0.005) {
                    continue;
                }
                $ada = true;
                $total[$kategori][0] += $cur;
                $total[$kategori][1] += $prev;
                $rows[] = self::baris('data', (string) $akun['nama'], [$cur, $prev], [
                    'kode'   => (string) $akun['kode'],
                    'level'  => 1,
                    'persen' => self::persen($cur, $prev),
                ]);
            }
            if (!$ada) {
                $rows[] = self::baris('data', 'Belum ada transaksi pada periode ini', [0, 0], ['level' => 1]);
            }
            $rows[] = self::baris('subtotal', 'Total ' . self::KATEGORI[$kategori], $total[$kategori]);
        }

        $labaKotor = [
            $totalPendapatan[0] - $total['harga_pokok'][0],
            $totalPendapatan[1] - $total['harga_pokok'][1],
        ];
        $labaOperasional = [
            $labaKotor[0] - $total['beban_operasional'][0],
            $labaKotor[1] - $total['beban_operasional'][1],
        ];
        $labaSebelumPajak = [
            $labaOperasional[0] - $total['beban_lain'][0],
            $labaOperasional[1] - $total['beban_lain'][1],
        ];
        $labaBersih = [
            $labaSebelumPajak[0] - $total['pajak'][0],
            $labaSebelumPajak[1] - $total['pajak'][1],
        ];

        $rows[] = self::baris('subtotal', 'LABA KOTOR', $labaKotor);
        $rows[] = self::baris('spacer', '');
        $rows[] = self::baris('subtotal', 'LABA OPERASIONAL', $labaOperasional);
        $rows[] = self::baris('subtotal', 'LABA SEBELUM PAJAK', $labaSebelumPajak);
        $rows[] = self::baris('total', 'LABA (RUGI) BERSIH', $labaBersih);

        $margin = [
            $totalPendapatan[0] > 0 ? ($labaBersih[0] / $totalPendapatan[0]) * 100 : 0.0,
            $totalPendapatan[1] > 0 ? ($labaBersih[1] / $totalPendapatan[1]) * 100 : 0.0,
        ];

        $kpi = [
            ['label' => 'Total Pendapatan', 'nilai' => $totalPendapatan[0], 'tipe' => 'uang', 'hint' => 'Periode ini'],
            ['label' => 'Laba Kotor', 'nilai' => $labaKotor[0], 'tipe' => 'uang', 'hint' => self::pctLabel($totalPendapatan[0] > 0 ? ($labaKotor[0] / $totalPendapatan[0]) * 100 : 0) . ' dari pendapatan'],
            ['label' => 'Laba Bersih', 'nilai' => $labaBersih[0], 'tipe' => 'uang', 'tone' => $labaBersih[0] < 0 ? 'down' : 'up', 'hint' => self::pctLabel($margin[0]) . ' net margin'],
            ['label' => 'Perubahan Laba', 'nilai' => self::persen($labaBersih[0], $labaBersih[1]), 'tipe' => 'persen', 'tone' => $labaBersih[0] >= $labaBersih[1] ? 'up' : 'down', 'hint' => 'vs ' . $rentang['prev_label']],
        ];

        return self::dataset('laba-rugi', $periode, $rentang, self::kolomBanding($rentang), $rows, $kpi, [
            'Angka dalam Rupiah penuh, dihitung dari jurnal berstatus posted.',
            'Nilai negatif ditampilkan dalam tanda kurung.',
        ], [
            'laba_kotor'  => $labaKotor[0],
            'laba_bersih' => $labaBersih[0],
            'margin'      => $margin[0],
            'pendapatan'  => $totalPendapatan[0],
            'beban_total' => $total['harga_pokok'][0] + $total['beban_operasional'][0] + $total['beban_lain'][0] + $total['pajak'][0],
        ]);
    }


    /* ------------------------------------------------------ UNIT USAHA */

    /**
     * Konsolidasi laba rugi dan posisi keuangan per unit usaha.
     *
     * @return array<string,mixed>
     */
    public static function unitUsaha(int $periodeId = 0, string $mode = 'bulan', array $filter = []): array
    {
        $periode = self::periode($periodeId);
        $rentang = self::rentang($periode, $mode);
        [$dimensi, $params] = self::dimensi($filter);

        $aliran = Db::all(
            "SELECT jl.business_unit_id,
                    COALESCE(SUM(CASE WHEN c.tipe = 'pendapatan' THEN jl.kredit - jl.debit ELSE 0 END), 0) AS pendapatan,
                    COALESCE(SUM(CASE WHEN c.tipe = 'beban' THEN jl.debit - jl.kredit ELSE 0 END), 0) AS beban
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal BETWEEN ? AND ?" . $dimensi . "
             GROUP BY jl.business_unit_id",
            array_merge([$rentang['from'], $rentang['to']], $params)
        );

        $posisi = Db::all(
            "SELECT jl.business_unit_id,
                    COALESCE(SUM(CASE WHEN c.tipe = 'aset' THEN jl.debit - jl.kredit ELSE 0 END), 0) AS aset,
                    COALESCE(SUM(CASE WHEN c.tipe = 'kewajiban' THEN jl.kredit - jl.debit ELSE 0 END), 0) AS kewajiban
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal <= ?" . $dimensi . "
             GROUP BY jl.business_unit_id",
            array_merge([$rentang['to']], $params)
        );

        $units = Db::all('SELECT id, kode, nama FROM ms_business_units ORDER BY kode');
        $nama = [];
        foreach ($units as $unit) {
            $nama[(int) $unit['id']] = (string) $unit['nama'];
        }

        $barisMap = [];
        foreach ($aliran as $row) {
            $id = (int) ($row['business_unit_id'] ?? 0);
            $barisMap[$id] = [
                'id'         => $id,
                'unit'       => $id > 0 ? ($nama[$id] ?? 'Unit #' . $id) : 'Tanpa Unit Usaha',
                'pendapatan' => (float) $row['pendapatan'],
                'beban'      => (float) $row['beban'],
                'aset'       => 0.0,
                'kewajiban'  => 0.0,
            ];
        }
        foreach ($posisi as $row) {
            $id = (int) ($row['business_unit_id'] ?? 0);
            $barisMap[$id] ??= [
                'id'         => $id,
                'unit'       => $id > 0 ? ($nama[$id] ?? 'Unit #' . $id) : 'Tanpa Unit Usaha',
                'pendapatan' => 0.0,
                'beban'      => 0.0,
                'aset'       => 0.0,
                'kewajiban'  => 0.0,
            ];
            $barisMap[$id]['aset'] = (float) $row['aset'];
            $barisMap[$id]['kewajiban'] = (float) $row['kewajiban'];
        }

        $totalPendapatan = 0.0;
        $totalBeban = 0.0;
        $totalAset = 0.0;
        $totalKewajiban = 0.0;
        foreach ($barisMap as &$row) {
            $row['laba'] = $row['pendapatan'] - $row['beban'];
            $row['margin'] = $row['pendapatan'] > 0 ? ($row['laba'] / $row['pendapatan']) * 100 : 0.0;
            $totalPendapatan += $row['pendapatan'];
            $totalBeban += $row['beban'];
            $totalAset += $row['aset'];
            $totalKewajiban += $row['kewajiban'];
        }
        unset($row);

        foreach ($barisMap as &$row) {
            $row['kontribusi'] = $totalPendapatan > 0 ? ($row['pendapatan'] / $totalPendapatan) * 100 : 0.0;
        }
        unset($row);

        usort($barisMap, static fn (array $a, array $b): int => $b['pendapatan'] <=> $a['pendapatan']);

        $rows = [];
        foreach ($barisMap as $row) {
            $rows[] = self::baris('data', $row['unit'], [
                $row['pendapatan'], $row['beban'], $row['laba'], $row['margin'], $row['aset'], $row['kewajiban'],
            ], [
                'kode'   => $row['id'] > 0 ? 'BU-' . str_pad((string) $row['id'], 2, '0', STR_PAD_LEFT) : '—',
                'level'  => 1,
                'persen' => $row['kontribusi'],
            ]);
        }

        $rows[] = self::baris('total', 'TOTAL KONSOLIDASI', [
            $totalPendapatan, $totalBeban, $totalPendapatan - $totalBeban,
            $totalPendapatan > 0 ? (($totalPendapatan - $totalBeban) / $totalPendapatan) * 100 : 0.0,
            $totalAset, $totalKewajiban,
        ]);

        $laba = $totalPendapatan - $totalBeban;

        $kpi = [
            ['label' => 'Unit Usaha', 'nilai' => count($units), 'tipe' => 'angka', 'hint' => 'Unit aktif'],
            ['label' => 'Pendapatan Konsolidasi', 'nilai' => $totalPendapatan, 'tipe' => 'uang', 'hint' => $rentang['label']],
            ['label' => 'Laba Konsolidasi', 'nilai' => $laba, 'tipe' => 'uang', 'tone' => $laba >= 0 ? 'up' : 'down', 'hint' => self::pctLabel($totalPendapatan > 0 ? ($laba / $totalPendapatan) * 100 : 0) . ' margin'],
            ['label' => 'Total Aset', 'nilai' => $totalAset, 'tipe' => 'uang', 'hint' => 'Per ' . date('d M Y', strtotime($rentang['to']))],
        ];

        return self::dataset('unit-usaha', $periode, $rentang, [
            ['label' => 'Unit Usaha', 'align' => 'left', 'lebar' => 26],
            ['label' => 'Pendapatan', 'align' => 'right', 'lebar' => 14],
            ['label' => 'Beban', 'align' => 'right', 'lebar' => 14],
            ['label' => 'Laba', 'align' => 'right', 'lebar' => 13],
            ['label' => 'Margin', 'align' => 'right', 'lebar' => 10],
            ['label' => 'Aset', 'align' => 'right', 'lebar' => 12],
            ['label' => 'Kewajiban', 'align' => 'right', 'lebar' => 11],
        ], $rows, $kpi, [
            'Setiap baris jurnal posted dapat ditandai unit usaha; transaksi tanpa penanda disajikan sebagai Tanpa Unit Usaha.',
            'Laba konsolidasi dihitung dari seluruh jurnal posted pada rentang tanggal tanpa eliminasi antar-unit.',
            'Angka aset dan kewajiban memakai saldo kumulatif sampai tanggal akhir periode.',
        ], [
            'total_pendapatan' => $totalPendapatan,
            'total_beban'      => $totalBeban,
            'laba'             => $laba,
            'total_aset'       => $totalAset,
            'total_kewajiban'  => $totalKewajiban,
            'jumlah_unit'      => count($units),
            'baris_unit'       => $barisMap,
        ]);
    }

    /* ---------------------------------------------------------------- NERACA */

    /** @return array<string,mixed> */
    public static function neraca(array $periode, array $rentang, array $filter = []): array
    {
        $asOf = $rentang['to'];
        $prevAsOf = $rentang['prev_to'];

        $current = self::saldoAkun($asOf, $filter);
        $previous = self::saldoAkun($prevAsOf, $filter);

        $rows = [];
        $totalAset = [0.0, 0.0];
        $totalKewajiban = [0.0, 0.0];

        foreach (['aset_lancar', 'aset_tetap', 'aset_lain'] as $kategori) {
            $rows[] = self::baris('section', strtoupper(self::KATEGORI[$kategori]));
            $subtotal = [0.0, 0.0];
            foreach ($current + $previous as $kode => $akun) {
                $akun = $current[$kode] ?? $previous[$kode];
                if ((string) $akun['tipe'] !== 'aset' || (string) $akun['kategori'] !== $kategori) {
                    continue;
                }
                $cur = (float) ($current[$kode]['saldo'] ?? 0.0);
                $prev = (float) ($previous[$kode]['saldo'] ?? 0.0);
                if (abs($cur) < 0.005 && abs($prev) < 0.005) {
                    continue;
                }
                $subtotal[0] += $cur;
                $subtotal[1] += $prev;
                $rows[] = self::baris('data', (string) $akun['nama'], [$cur, $prev], [
                    'kode'   => (string) $akun['kode'],
                    'level'  => 1,
                    'persen' => self::persen($cur, $prev),
                ]);
            }
            if (abs($subtotal[0]) < 0.005 && abs($subtotal[1]) < 0.005) {
                $rows[] = self::baris('data', 'Tidak ada saldo', [0, 0], ['level' => 1]);
            }
            $rows[] = self::baris('subtotal', 'Jumlah ' . self::KATEGORI[$kategori], $subtotal);
            $totalAset[0] += $subtotal[0];
            $totalAset[1] += $subtotal[1];
        }

        $rows[] = self::baris('total', 'TOTAL ASET', $totalAset);
        $rows[] = self::baris('spacer', '');

        foreach (['kewajiban_lancar', 'kewajiban_panjang'] as $kategori) {
            $rows[] = self::baris('section', strtoupper(self::KATEGORI[$kategori]));
            $subtotal = [0.0, 0.0];
            foreach ($current + $previous as $kode => $akun) {
                $akun = $current[$kode] ?? $previous[$kode];
                if ((string) $akun['tipe'] !== 'kewajiban' || (string) $akun['kategori'] !== $kategori) {
                    continue;
                }
                $cur = self::nilaiAkun('kewajiban', (float) ($current[$kode]['saldo'] ?? 0.0));
                $prev = self::nilaiAkun('kewajiban', (float) ($previous[$kode]['saldo'] ?? 0.0));
                if (abs($cur) < 0.005 && abs($prev) < 0.005) {
                    continue;
                }
                $subtotal[0] += $cur;
                $subtotal[1] += $prev;
                $rows[] = self::baris('data', (string) $akun['nama'], [$cur, $prev], [
                    'kode'   => (string) $akun['kode'],
                    'level'  => 1,
                    'persen' => self::persen($cur, $prev),
                ]);
            }
            if (abs($subtotal[0]) < 0.005 && abs($subtotal[1]) < 0.005) {
                $rows[] = self::baris('data', 'Tidak ada saldo', [0, 0], ['level' => 1]);
            }
            $rows[] = self::baris('subtotal', 'Jumlah ' . self::KATEGORI[$kategori], $subtotal);
            $totalKewajiban[0] += $subtotal[0];
            $totalKewajiban[1] += $subtotal[1];
        }

        $rows[] = self::baris('subtotal', 'TOTAL KEWAJIBAN', $totalKewajiban);
        $rows[] = self::baris('spacer', '');

        $rows[] = self::baris('section', 'EKUITAS');
        $totalEkuitas = [0.0, 0.0];
        foreach ($current + $previous as $kode => $akun) {
            $akun = $current[$kode] ?? $previous[$kode];
            if ((string) $akun['tipe'] !== 'ekuitas') {
                continue;
            }
            $cur = self::nilaiAkun('ekuitas', (float) ($current[$kode]['saldo'] ?? 0.0));
            $prev = self::nilaiAkun('ekuitas', (float) ($previous[$kode]['saldo'] ?? 0.0));
            if (abs($cur) < 0.005 && abs($prev) < 0.005) {
                continue;
            }
            $totalEkuitas[0] += $cur;
            $totalEkuitas[1] += $prev;
            $rows[] = self::baris('data', (string) $akun['nama'], [$cur, $prev], [
                'kode'   => (string) $akun['kode'],
                'level'  => 1,
                'persen' => self::persen($cur, $prev),
            ]);
        }

        $laba = [self::labaKumulatif($asOf, $filter), self::labaKumulatif($prevAsOf, $filter)];
        if (abs($laba[0]) >= 0.005 || abs($laba[1]) >= 0.005) {
            $totalEkuitas[0] += $laba[0];
            $totalEkuitas[1] += $laba[1];
            $rows[] = self::baris('data', 'Laba (Rugi) Tahun Berjalan', $laba, [
                'level'  => 1,
                'persen' => self::persen($laba[0], $laba[1]),
                'catatan' => 'Belum ditutup ke saldo laba',
            ]);
        }

        $rows[] = self::baris('total', 'TOTAL EKUITAS', $totalEkuitas);
        $rows[] = self::baris('total', 'TOTAL KEWAJIBAN DAN EKUITAS', [
            $totalKewajiban[0] + $totalEkuitas[0],
            $totalKewajiban[1] + $totalEkuitas[1],
        ]);

        $selisih = round($totalAset[0] - ($totalKewajiban[0] + $totalEkuitas[0]), 2);
        $seimbang = abs($selisih) < 0.005;

        $asetLancar = self::kategoriTotal($current, 'aset_lancar');
        $kewajibanLancar = self::kategoriTotal($current, 'kewajiban_lancar', 'kewajiban');
        $rasioLancar = $kewajibanLancar > 0 ? $asetLancar / $kewajibanLancar : null;

        $kpi = [
            ['label' => 'Total Aset', 'nilai' => $totalAset[0], 'tipe' => 'uang', 'hint' => 'Per ' . date('d M Y', strtotime($asOf))],
            ['label' => 'Kewajiban', 'nilai' => $totalKewajiban[0], 'tipe' => 'uang', 'hint' => 'Jangka pendek & panjang'],
            ['label' => 'Ekuitas', 'nilai' => $totalEkuitas[0], 'tipe' => 'uang', 'tone' => 'up', 'hint' => 'Termasuk laba berjalan'],
            ['label' => 'Rasio Lancar', 'nilai' => $rasioLancar, 'tipe' => 'kali', 'tone' => ($rasioLancar ?? 0) >= 1 ? 'up' : 'down', 'hint' => 'Aset lancar ÷ kewajiban lancar'],
        ];

        return self::dataset('neraca', $periode, $rentang, self::kolomBanding($rentang, 'Per ', 'Per ' . date('d M Y', strtotime($rentang['to']))), $rows, $kpi, [
            'Neraca disusun dari saldo kumulatif jurnal posted sampai tanggal laporan.',
            'Laba tahun berjalan yang belum ditutup disajikan sebagai bagian ekuitas.',
        ], [
            'total_aset'      => $totalAset[0],
            'total_kewajiban' => $totalKewajiban[0],
            'total_ekuitas'   => $totalEkuitas[0],
            'selisih'         => $selisih,
            'seimbang'        => $seimbang,
            'aset_lancar'     => $asetLancar,
            'kewajiban_lancar' => $kewajibanLancar,
            'rasio_lancar'    => $rasioLancar,
        ]);
    }

    /** Total saldo satu kategori (nilai tampilan). */
    private static function kategoriTotal(array $akun, string $kategori, string $tipe = 'aset'): float
    {
        $total = 0.0;
        foreach ($akun as $row) {
            if ((string) $row['kategori'] === $kategori && (string) $row['tipe'] === $tipe) {
                $total += self::nilaiAkun($tipe, (float) $row['saldo']);
            }
        }
        return $total;
    }

    /** Laba (rugi) kumulatif sejak awal pembukuan sampai tanggal. */
    public static function labaKumulatif(string $to, array $filter = []): float
    {
        $akun = self::saldoAkun($to, $filter);
        $pendapatan = 0.0;
        $beban = 0.0;
        foreach ($akun as $row) {
            if ((string) $row['tipe'] === 'pendapatan') {
                $pendapatan += self::nilaiAkun('pendapatan', (float) $row['saldo']);
            } elseif ((string) $row['tipe'] === 'beban') {
                $beban += self::nilaiAkun('beban', (float) $row['saldo']);
            }
        }
        return round($pendapatan - $beban, 2);
    }

    /* ------------------------------------------------------------- ARUS KAS */

    /** @return array<string,mixed> */
    public static function arusKas(array $periode, array $rentang, array $filter = []): array
    {
        $current = self::arusKasKategori($rentang['from'], $rentang['to']);
        $previous = self::arusKasKategori($rentang['prev_from'], $rentang['prev_to']);

        $rows = [];
        $totals = ['operasi' => [0.0, 0.0], 'investasi' => [0.0, 0.0], 'pendanaan' => [0.0, 0.0]];

        foreach (self::ARUS_KAS as $kode => $label) {
            $rows[] = self::baris('section', strtoupper($label));
            $ada = false;
            $gabungan = $current[$kode] + $previous[$kode];
            foreach ($gabungan as $akunKode => $akunBaris) {
                $akun = $current[$kode][$akunKode] ?? $previous[$kode][$akunKode];
                $cur = (float) ($akun['nilai'] ?? 0.0);
                $prev = (float) ($previous[$kode][$akunKode]['nilai'] ?? 0.0);
                if (abs($cur) < 0.005 && abs($prev) < 0.005) {
                    continue;
                }
                $ada = true;
                $totals[$kode][0] += $cur;
                $totals[$kode][1] += $prev;
                $rows[] = self::baris('data', (string) $akun['nama'], [$cur, $prev], [
                    'kode'   => (string) $akun['kode'],
                    'level'  => 1,
                    'persen' => self::persen($cur, $prev),
                ]);
            }
            if (!$ada) {
                $rows[] = self::baris('data', 'Tidak ada arus kas', [0, 0], ['level' => 1]);
            }
            $rows[] = self::baris('subtotal', 'Kas Neto dari ' . str_replace('Arus Kas dari ', '', $label), $totals[$kode]);
        }

        $neto = [
            $totals['operasi'][0] + $totals['investasi'][0] + $totals['pendanaan'][0],
            $totals['operasi'][1] + $totals['investasi'][1] + $totals['pendanaan'][1],
        ];

        $kasAwal = [self::saldoKas($rentang['from'] . ' -1 day'), self::saldoKas($rentang['prev_from'] . ' -1 day')];
        $kasAkhir = [self::saldoKas($rentang['to']), self::saldoKas($rentang['prev_to'])];

        $rows[] = self::baris('total', 'KENAIKAN (PENURUNAN) KAS NETO', $neto);
        $rows[] = self::baris('data', 'Kas & Bank Awal Periode', $kasAwal, ['level' => 0]);
        $rows[] = self::baris('total', 'KAS & BANK AKHIR PERIODE', $kasAkhir);

        // Rekonsiliasi tidak langsung.
        $laba = self::labaRentang($rentang, $filter);
        $nonKas = self::nonKas($rentang['from'], $rentang['to']);
        $modalKerja = self::perubahanModalKerja($rentang['from'], $rentang['to']);
        $estimasiOperasi = round($laba + $nonKas + $modalKerja, 2);
        $selisih = round($estimasiOperasi - $totals['operasi'][0], 2);

        $rows[] = self::baris('spacer', '');
        $rows[] = self::baris('section', 'REKONSILIASI ARUS KAS OPERASI (METODE TIDAK LANGSUNG)');
        $rows[] = self::baris('data', 'Laba (Rugi) Bersih', [$laba, null], ['level' => 1]);
        $rows[] = self::baris('data', 'Penyusutan & Beban Non-Kas', [$nonKas, null], ['level' => 1]);
        $rows[] = self::baris('data', 'Perubahan Modal Kerja', [$modalKerja, null], ['level' => 1]);
        $rows[] = self::baris('subtotal', 'Estimasi Kas dari Operasi', [$estimasiOperasi, null]);
        $rows[] = self::baris('data', 'Selisih terhadap metode langsung', [$selisih, null], ['level' => 0]);

        $kpi = [
            ['label' => 'Kas Awal', 'nilai' => $kasAwal[0], 'tipe' => 'uang', 'hint' => 'Awal periode'],
            ['label' => 'Kas dari Operasi', 'nilai' => $totals['operasi'][0], 'tipe' => 'uang', 'tone' => $totals['operasi'][0] >= 0 ? 'up' : 'down', 'hint' => 'Aktivitas utama'],
            ['label' => 'Kas Neto', 'nilai' => $neto[0], 'tipe' => 'uang', 'tone' => $neto[0] >= 0 ? 'up' : 'down', 'hint' => 'Operasi + investasi + pendanaan'],
            ['label' => 'Kas Akhir', 'nilai' => $kasAkhir[0], 'tipe' => 'uang', 'tone' => 'up', 'hint' => 'Per ' . date('d M Y', strtotime($rentang['to']))],
        ];

        return self::dataset('arus-kas', $periode, $rentang, self::kolomBanding($rentang), $rows, $kpi, [
            'Metode langsung: arus kas dialokasikan dari lawan transaksi jurnal yang melibatkan akun kas/bank.',
            'Rekonsiliasi tidak langsung disajikan sebagai kontrol; selisih kecil dapat terjadi karena pemetaan kategori akun.',
        ], [
            'neto'           => $neto[0],
            'operasi'        => $totals['operasi'][0],
            'investasi'      => $totals['investasi'][0],
            'pendanaan'      => $totals['pendanaan'][0],
            'kas_awal'       => $kasAwal[0],
            'kas_akhir'      => $kasAkhir[0],
            'rekon_selisih'  => $selisih,
        ]);
    }

    /**
     * Arus kas per kategori, dikelompokkan per akun lawan.
     *
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function arusKasKategori(string $from, string $to): array
    {
        $hasil = ['operasi' => [], 'investasi' => [], 'pendanaan' => []];

        $rows = Db::all(
            "SELECT c.kode, c.nama, c.tipe, c.kategori, c.arus_kas,
                    COALESCE(SUM(jl.debit), 0) AS debit,
                    COALESCE(SUM(jl.kredit), 0) AS kredit
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal BETWEEN ? AND ? AND c.is_kas_bank = 0
               AND je.id IN (
                   SELECT DISTINCT jl2.journal_id
                   FROM journal_lines jl2
                   JOIN coa_accounts c2 ON c2.id = jl2.coa_id
                   WHERE c2.is_kas_bank = 1
               )
             GROUP BY c.kode, c.nama, c.tipe, c.kategori, c.arus_kas
             ORDER BY c.kode",
            [$from, $to]
        );

        foreach ($rows as $row) {
            $arus = (string) $row['arus_kas'];
            if (!isset($hasil[$arus])) {
                $arus = 'operasi';
            }
            $nilai = -((float) $row['debit'] - (float) $row['kredit']);
            if (abs($nilai) < 0.005) {
                continue;
            }
            $kode = (string) $row['kode'];
            $hasil[$arus][$kode] = [
                'kode'  => $kode,
                'nama'  => (string) $row['nama'],
                'nilai' => round($nilai, 2),
            ];
        }

        foreach ($hasil as $arus => $items) {
            uasort($items, static fn (array $a, array $b): int => abs($b['nilai']) <=> abs($a['nilai']));
            $hasil[$arus] = $items;
        }

        return $hasil;
    }

    public static function saldoKas(string $date): float
    {
        return (float) Db::val(
            "SELECT COALESCE(SUM(jl.debit - jl.kredit), 0)
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND c.is_kas_bank = 1 AND je.tanggal <= ?",
            [$date]
        );
    }

    /** Beban non-kas (mis. penyusutan) pada rentang tanggal. */
    public static function nonKas(string $from, string $to): float
    {
        return (float) Db::val(
            "SELECT COALESCE(SUM(jl.debit - jl.kredit), 0)
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal BETWEEN ? AND ?
               AND c.arus_kas = 'non_kas' AND c.tipe = 'beban'",
            [$from, $to]
        );
    }

    /** Perubahan modal kerja (aset lancar & kewajiban lancar non-kas). */
    public static function perubahanModalKerja(string $from, string $to): float
    {
        $awal = self::saldoAkun(date('Y-m-d', strtotime($from . ' -1 day')));
        $akhir = self::saldoAkun($to);

        $delta = 0.0;
        foreach ($akhir as $kode => $row) {
            $tipe = (string) $row['tipe'];
            $kategori = (string) $row['kategori'];
            if ((int) $row['is_kas_bank'] === 1) {
                continue;
            }
            $perubahan = (float) $row['saldo'] - (float) ($awal[$kode]['saldo'] ?? 0.0);
            if ($kategori === 'aset_lancar' && $tipe === 'aset') {
                $delta -= $perubahan;
            } elseif ($kategori === 'kewajiban_lancar' && $tipe === 'kewajiban') {
                $delta -= $perubahan;
            }
        }
        return round($delta, 2);
    }

    /** Laba rugi rentang tanggal (pendapatan − beban). */
    public static function labaRentang(array $rentang, array $filter = []): float
    {
        $akun = self::mutasiAkun($rentang['from'], $rentang['to'], $filter);
        $pendapatan = 0.0;
        $beban = 0.0;
        foreach ($akun as $row) {
            if ((string) $row['tipe'] === 'pendapatan') {
                $pendapatan += self::nilaiAkun('pendapatan', (float) $row['saldo']);
            } elseif ((string) $row['tipe'] === 'beban') {
                $beban += self::nilaiAkun('beban', (float) $row['saldo']);
            }
        }
        return round($pendapatan - $beban, 2);
    }

    /* ------------------------------------------------------------- EKUITAS */

    /** @return array<string,mixed> */
    public static function ekuitas(array $periode, array $rentang, array $filter = []): array
    {
        $sebelum = date('Y-m-d', strtotime($rentang['from'] . ' -1 day'));
        $akunAwal = self::saldoAkun($sebelum, $filter);
        $akunAkhir = self::saldoAkun($rentang['to'], $filter);

        $saldoAwalAkun = 0.0;
        foreach ($akunAwal as $row) {
            if ((string) $row['tipe'] === 'ekuitas') {
                $saldoAwalAkun += self::nilaiAkun('ekuitas', (float) $row['saldo']);
            }
        }
        $labaSebelum = self::labaKumulatif($sebelum, $filter);
        $saldoAwal = round($saldoAwalAkun + $labaSebelum, 2);

        $labaPeriode = self::labaRentang($rentang, $filter);

        $mutasi = [];
        foreach ($akunAkhir as $kode => $row) {
            if ((string) $row['tipe'] !== 'ekuitas') {
                continue;
            }
            $awal = self::nilaiAkun('ekuitas', (float) ($akunAwal[$kode]['saldo'] ?? 0.0));
            $akhir = self::nilaiAkun('ekuitas', (float) $row['saldo']);
            if (abs($awal) < 0.005 && abs($akhir) < 0.005) {
                continue;
            }
            $mutasi[] = [
                'kode'  => (string) $row['kode'],
                'nama'  => (string) $row['nama'],
                'awal'  => $awal,
                'tambah' => max(0.0, $akhir - $awal),
                'kurang' => max(0.0, $awal - $akhir),
                'akhir' => $akhir,
            ];
        }

        $saldoAkhirAkun = 0.0;
        foreach ($akunAkhir as $row) {
            if ((string) $row['tipe'] === 'ekuitas') {
                $saldoAkhirAkun += self::nilaiAkun('ekuitas', (float) $row['saldo']);
            }
        }
        $labaAkhir = self::labaKumulatif($rentang['to'], $filter);
        $saldoAkhir = round($saldoAkhirAkun + $labaAkhir, 2);

        $rows = [];
        $rows[] = self::baris('section', 'SALDO AWAL EKUITAS');
        $rows[] = self::baris('data', 'Ekuitas pada ' . date('d M Y', strtotime($sebelum)), [$saldoAwal]);
        $rows[] = self::baris('spacer', '');

        $rows[] = self::baris('section', 'MUTASI PERIODE');
        $rows[] = self::baris('data', 'Laba (Rugi) Periode Berjalan', [$labaPeriode], ['level' => 1]);

        foreach ($mutasi as $item) {
            if (abs($item['tambah']) >= 0.005) {
                $rows[] = self::baris('data', 'Penambahan ' . $item['nama'], [$item['tambah']], ['kode' => $item['kode'], 'level' => 1]);
            }
            if (abs($item['kurang']) >= 0.005) {
                $rows[] = self::baris('data', 'Pengurangan ' . $item['nama'], [-$item['kurang']], ['kode' => $item['kode'], 'level' => 1]);
            }
        }

        $rows[] = self::baris('subtotal', 'Total Mutasi', [round($labaPeriode + array_sum(array_column($mutasi, 'tambah')) - array_sum(array_column($mutasi, 'kurang')), 2)]);
        $rows[] = self::baris('total', 'SALDO AKHIR EKUITAS', [$saldoAkhir]);

        $rows[] = self::baris('spacer', '');
        $rows[] = self::baris('section', 'RINCIAN SALDO AKHIR PER AKUN');
        foreach ($mutasi as $item) {
            $rows[] = self::baris('data', $item['nama'], [$item['akhir']], ['kode' => $item['kode'], 'level' => 1]);
        }
        if (abs($labaAkhir) >= 0.005) {
            $rows[] = self::baris('data', 'Laba (Rugi) belum ditutup', [$labaAkhir], ['level' => 1]);
        }
        $rows[] = self::baris('subtotal', 'Total Ekuitas Akhir', [$saldoAkhir]);

        $kpi = [
            ['label' => 'Ekuitas Awal', 'nilai' => $saldoAwal, 'tipe' => 'uang', 'hint' => 'Per ' . date('d M Y', strtotime($sebelum))],
            ['label' => 'Laba Periode', 'nilai' => $labaPeriode, 'tipe' => 'uang', 'tone' => $labaPeriode >= 0 ? 'up' : 'down', 'hint' => 'Menambah ekuitas'],
            ['label' => 'Perubahan', 'nilai' => round($saldoAkhir - $saldoAwal, 2), 'tipe' => 'uang', 'tone' => $saldoAkhir >= $saldoAwal ? 'up' : 'down', 'hint' => 'Termasuk setoran modal'],
            ['label' => 'Ekuitas Akhir', 'nilai' => $saldoAkhir, 'tipe' => 'uang', 'tone' => 'up', 'hint' => 'Per ' . date('d M Y', strtotime($rentang['to']))],
        ];

        $totalTambah = array_sum(array_column($mutasi, 'tambah'));
        $totalKurang = array_sum(array_column($mutasi, 'kurang'));

        return self::dataset('ekuitas', $periode, $rentang, [
            ['label' => 'Uraian', 'align' => 'left', 'lebar' => 76],
            ['label' => 'Jumlah', 'align' => 'right', 'lebar' => 24],
        ], $rows, $kpi, [
            'Saldo awal mencakup akumulasi laba tahun berjalan yang belum ditutup.',
            'Penambahan/pengurangan modal dihitung dari mutasi akun ekuitas pada periode.',
        ], [
            'saldo_awal'    => $saldoAwal,
            'laba'          => $labaPeriode,
            'laba_periode'  => $labaPeriode,
            'saldo_akhir'   => $saldoAkhir,
            'mutasi'        => $mutasi,
            'tambahan'      => $totalTambah,
            'pengurangan'   => $totalKurang,
            'total_mutasi'  => round($labaPeriode + $totalTambah - $totalKurang, 2),
        ]);
    }

    /* --------------------------------------------------------------- PROYEK */

    /** @return array<string,mixed> */
    public static function proyek(array $periode, array $rentang, array $filter = []): array
    {
        [$dimensi, $params] = self::dimensi($filter);

        $rows = Db::all(
            "SELECT p.id, p.kode, p.nama, p.status,
                    COALESCE(SUM(CASE WHEN c.tipe = 'pendapatan' THEN jl.kredit - jl.debit ELSE 0 END), 0) AS pendapatan,
                    COALESCE(SUM(CASE WHEN c.tipe = 'beban' THEN jl.debit - jl.kredit ELSE 0 END), 0) AS beban
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             JOIN ms_projects p ON p.id = jl.project_id
             WHERE je.status = 'posted' AND je.tanggal BETWEEN ? AND ?" . $dimensi . "
             GROUP BY p.id, p.kode, p.nama, p.status
             HAVING pendapatan <> 0 OR beban <> 0
             ORDER BY (pendapatan - beban) DESC",
            array_merge([$rentang['from'], $rentang['to']], $params)
        );

        $baris = [];
        $totalPendapatan = 0.0;
        $totalBeban = 0.0;

        foreach ($rows as $row) {
            $pendapatan = (float) $row['pendapatan'];
            $beban = (float) $row['beban'];
            $laba = $pendapatan - $beban;
            $totalPendapatan += $pendapatan;
            $totalBeban += $beban;
            $baris[] = self::baris('data', (string) $row['nama'], [$pendapatan, $beban, $laba, $pendapatan > 0 ? ($laba / $pendapatan) * 100 : 0.0], [
                'kode' => (string) $row['kode'],
            ]);
        }

        $tanpaProyek = Db::one(
            "SELECT COALESCE(SUM(CASE WHEN c.tipe = 'pendapatan' THEN jl.kredit - jl.debit ELSE 0 END), 0) AS pendapatan,
                    COALESCE(SUM(CASE WHEN c.tipe = 'beban' THEN jl.debit - jl.kredit ELSE 0 END), 0) AS beban
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = 'posted' AND je.tanggal BETWEEN ? AND ? AND jl.project_id IS NULL" . $dimensi,
            array_merge([$rentang['from'], $rentang['to']], $params)
        );

        if ($tanpaProyek !== null) {
            $pendapatanTanpa = (float) $tanpaProyek['pendapatan'];
            $bebanTanpa = (float) $tanpaProyek['beban'];
            if (abs($pendapatanTanpa) >= 0.005 || abs($bebanTanpa) >= 0.005) {
                $labaTanpa = $pendapatanTanpa - $bebanTanpa;
                $totalPendapatan += $pendapatanTanpa;
                $totalBeban += $bebanTanpa;
                $baris[] = self::baris('data', 'Tanpa Proyek', [$pendapatanTanpa, $bebanTanpa, $labaTanpa, $pendapatanTanpa > 0 ? ($labaTanpa / $pendapatanTanpa) * 100 : 0.0], ['kode' => '—']);
            }
        }

        $labaTotal = $totalPendapatan - $totalBeban;
        $baris[] = self::baris('total', 'TOTAL PROYEK', [$totalPendapatan, $totalBeban, $labaTotal, $totalPendapatan > 0 ? ($labaTotal / $totalPendapatan) * 100 : 0.0]);

        $catatan = [
            'Laba proyek dihitung dari akun pendapatan dan beban yang bertanda proyek pada jurnal posted.',
            'Transaksi tanpa dimensi proyek disajikan sebagai baris Tanpa Proyek agar total sama dengan laba rugi periode.',
        ];

        $kpi = [
            ['label' => 'Pendapatan Proyek', 'nilai' => $totalPendapatan, 'tipe' => 'uang', 'hint' => count($rows) . ' proyek'],
            ['label' => 'Beban Proyek', 'nilai' => $totalBeban, 'tipe' => 'uang', 'hint' => 'Biaya langsung'],
            ['label' => 'Laba Proyek', 'nilai' => $labaTotal, 'tipe' => 'uang', 'tone' => $labaTotal >= 0 ? 'up' : 'down', 'hint' => 'Pendapatan − beban'],
            ['label' => 'Margin Proyek', 'nilai' => $totalPendapatan > 0 ? ($labaTotal / $totalPendapatan) * 100 : null, 'tipe' => 'persen', 'tone' => $labaTotal >= 0 ? 'up' : 'down', 'hint' => 'Laba ÷ pendapatan'],
        ];

        return self::dataset('proyek', $periode, $rentang, [
            ['label' => 'Proyek', 'align' => 'left', 'lebar' => 42],
            ['label' => 'Pendapatan', 'align' => 'right', 'lebar' => 20],
            ['label' => 'Beban', 'align' => 'right', 'lebar' => 20],
            ['label' => 'Laba (Rugi)', 'align' => 'right', 'lebar' => 18],
            ['label' => 'Margin', 'align' => 'right', 'lebar' => 10],
        ], $baris, $kpi, $catatan, [
            'pendapatan'    => $totalPendapatan,
            'beban'         => $totalBeban,
            'laba'          => $labaTotal,
            'margin'        => $totalPendapatan > 0 ? ($labaTotal / $totalPendapatan) * 100 : 0.0,
            'jumlah'        => count($rows),
            'jumlah_proyek' => count($rows),
        ]);
    }

    /* ---------------------------------------------------------------- RASIO */

    /**
     * Rasio keuangan utama dari neraca & laba rugi periode.
     *
     * @return array<int,array{label:string,nilai:float|null,format:string,hint:string,tone:string}>
     */
    public static function rasio(int $periodeId = 0, array $filter = []): array
    {
        $periode = self::periode($periodeId);
        $rentang = self::rentang($periode, 'bulan');
        $neraca = self::neraca($periode, $rentang, $filter);
        $labaRugi = self::labaRugi($periode, $rentang, $filter);

        $asetLancar = (float) ($neraca['aset_lancar'] ?? 0);
        $kewajibanLancar = (float) ($neraca['kewajiban_lancar'] ?? 0);
        $totalAset = (float) ($neraca['total_aset'] ?? 0);
        $totalKewajiban = (float) ($neraca['total_kewajiban'] ?? 0);
        $totalEkuitas = (float) ($neraca['total_ekuitas'] ?? 0);
        $labaBersih = (float) ($labaRugi['laba_bersih'] ?? 0);
        $pendapatan = (float) ($labaRugi['pendapatan'] ?? 0);
        $kas = self::saldoKas($rentang['to']);

        $rasio = [];
        $rasio[] = [
            'label'  => 'Rasio Lancar',
            'nilai'  => $kewajibanLancar > 0 ? $asetLancar / $kewajibanLancar : null,
            'format' => 'kali',
            'hint'   => 'Aset lancar ÷ kewajiban lancar',
            'tone'   => ($kewajibanLancar > 0 && $asetLancar / $kewajibanLancar >= 1) ? 'up' : 'down',
        ];
        $rasio[] = [
            'label'  => 'Rasio Kas',
            'nilai'  => $kewajibanLancar > 0 ? $kas / $kewajibanLancar : null,
            'format' => 'kali',
            'hint'   => 'Kas & bank ÷ kewajiban lancar',
            'tone'   => ($kewajibanLancar > 0 && $kas / $kewajibanLancar >= 0.2) ? 'up' : 'netral',
        ];
        $rasio[] = [
            'label'  => 'Debt to Equity',
            'nilai'  => $totalEkuitas > 0 ? $totalKewajiban / $totalEkuitas : null,
            'format' => 'kali',
            'hint'   => 'Total kewajiban ÷ ekuitas',
            'tone'   => ($totalEkuitas > 0 && $totalKewajiban / $totalEkuitas <= 2) ? 'up' : 'down',
        ];
        $rasio[] = [
            'label'  => 'Net Profit Margin',
            'nilai'  => $pendapatan > 0 ? ($labaBersih / $pendapatan) * 100 : null,
            'format' => 'persen',
            'hint'   => 'Laba bersih ÷ pendapatan',
            'tone'   => $labaBersih >= 0 ? 'up' : 'down',
        ];
        $rasio[] = [
            'label'  => 'Return on Assets',
            'nilai'  => $totalAset > 0 ? ($labaBersih / $totalAset) * 100 : null,
            'format' => 'persen',
            'hint'   => 'Laba bersih ÷ total aset',
            'tone'   => $labaBersih >= 0 ? 'up' : 'down',
        ];
        $rasio[] = [
            'label'  => 'Return on Equity',
            'nilai'  => $totalEkuitas > 0 ? ($labaBersih / $totalEkuitas) * 100 : null,
            'format' => 'persen',
            'hint'   => 'Laba bersih ÷ ekuitas',
            'tone'   => $labaBersih >= 0 ? 'up' : 'down',
        ];

        return $rasio;
    }

    /* --------------------------------------------------------------- DASHBOARD */

    /**
     * Tren 6 bulan pendapatan, beban, dan laba.
     *
     * @return array{bulan:array<int,array<string,mixed>>,maks:float,banding:array<string,mixed>}
     */
    public static function tren(int $bulan = 6, ?array $periode = null): array
    {
        $periode ??= self::periode(0);
        $to = (string) ($periode['tanggal_selesai'] ?? date('Y-m-t'));

        $data = [];
        for ($i = $bulan - 1; $i >= 0; $i--) {
            $start = date('Y-m-01', strtotime($to . ' -' . $i . ' month'));
            $end = date('Y-m-t', strtotime($start));
            $akun = self::mutasiAkun($start, $end);

            $pendapatan = 0.0;
            $beban = 0.0;
            foreach ($akun as $row) {
                if ((string) $row['tipe'] === 'pendapatan') {
                    $pendapatan += self::nilaiAkun('pendapatan', (float) $row['saldo']);
                } elseif ((string) $row['tipe'] === 'beban') {
                    $beban += self::nilaiAkun('beban', (float) $row['saldo']);
                }
            }

            $data[] = [
                'label'      => self::bulanSingkat((int) date('n', strtotime($start))) . ' ' . date('y', strtotime($start)),
                'label_full' => self::bulanSingkat((int) date('n', strtotime($start))) . ' ' . date('Y', strtotime($start)),
                'from'       => $start,
                'to'         => $end,
                'pendapatan' => round($pendapatan, 2),
                'beban'      => round($beban, 2),
                'laba'       => round($pendapatan - $beban, 2),
            ];
        }

        $maks = 0.0;
        foreach ($data as $row) {
            $maks = max($maks, $row['pendapatan'], $row['beban']);
        }

        $akhir = $data[count($data) - 1] ?? ['pendapatan' => 0, 'beban' => 0, 'laba' => 0];
        $sebelum = $data[count($data) - 2] ?? ['pendapatan' => 0, 'beban' => 0, 'laba' => 0];

        return [
            'bulan'   => $data,
            'maks'    => $maks > 0 ? $maks : 1,
            'banding' => [
                'pendapatan' => self::persen((float) $akhir['pendapatan'], (float) $sebelum['pendapatan']),
                'beban'      => self::persen((float) $akhir['beban'], (float) $sebelum['beban']),
                'laba'       => self::persen((float) $akhir['laba'], (float) $sebelum['laba']),
                'pendapatan_nilai' => (float) $akhir['pendapatan'],
                'beban_nilai'      => (float) $akhir['beban'],
                'laba_nilai'       => (float) $akhir['laba'],
                'pendapatan_delta' => (float) $akhir['pendapatan'] - (float) $sebelum['pendapatan'],
                'beban_delta'      => (float) $akhir['beban'] - (float) $sebelum['beban'],
                'laba_delta'       => (float) $akhir['laba'] - (float) $sebelum['laba'],
            ],
        ];
    }

    /**
     * Ringkasan singkat untuk halaman hub laporan.
     *
     * @return array<string,mixed>
     */
    public static function ringkasan(int $periodeId = 0): array
    {
        $periode = self::periode($periodeId);
        $rentang = self::rentang($periode, 'bulan');
        $labaRugi = self::labaRugi($periode, $rentang);
        $neraca = self::neraca($periode, $rentang);
        $arusKas = self::arusKas($periode, $rentang);

        return [
            'periode'    => $periode,
            'rentang'    => $rentang,
            'laba_rugi'  => $labaRugi,
            'neraca'     => $neraca,
            'arus_kas'   => $arusKas,
            'rasio'      => self::rasio($periodeId),
            'tren'       => self::tren(6, $periode),
        ];
    }

    /** Nama bulan singkat bahasa Indonesia. */
    public static function bulanSingkat(int $bulan): string
    {
        $nama = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return $nama[$bulan] ?? (string) $bulan;
    }

    /** Label persen dengan satu desimal. */
    public static function pctLabel(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return number_format($value, 1, ',', '.') . '%';
    }

    /** Format nilai KPI sesuai tipe. */
    public static function formatNilai(mixed $nilai, string $tipe): string
    {
        if ($nilai === null || $nilai === '') {
            return '-';
        }
        return match ($tipe) {
            'uang'   => 'Rp ' . number_format((float) $nilai, 0, ',', '.'),
            'persen' => self::pctLabel((float) $nilai),
            'kali'   => number_format((float) $nilai, 2, ',', '.') . '×',
            default  => (string) $nilai,
        };
    }

    /**
     * Kolom pembanding standar.
     *
     * @return array<int,array<string,string|float>>
     */
    private static function kolomBanding(array $rentang, string $prefixPrev = '', string $labelCurrent = 'Periode Ini'): array
    {
        return [
            ['label' => 'Uraian', 'align' => 'left', 'lebar' => 46],
            ['label' => $labelCurrent, 'align' => 'right', 'lebar' => 20],
            ['label' => $prefixPrev . date('d M Y', strtotime($rentang['prev_to'])), 'align' => 'right', 'lebar' => 20],
            ['label' => 'Δ %', 'align' => 'right', 'lebar' => 14],
        ];
    }
}
