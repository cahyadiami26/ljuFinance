<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Query buku besar, buku besar pembantu (per dimensi), dan neraca saldo.
 *
 * Seluruh laporan dihitung langsung dari journal_lines berstatus 'posted'
 * sehingga selalu konsisten dengan jurnal — account_period_balances hanya
 * dipakai sebagai cache untuk kebutuhan cepat.
 *
 * Konvensi: debit positif, kredit negatif.
 */
final class LedgerService
{
    /** @return array<int,array<string,mixed>> */
    public static function akunPosting(bool $hanyaAktif = true): array
    {
        $sql = 'SELECT id, kode, nama, tipe, kategori, saldo_normal FROM coa_accounts WHERE allow_posting = 1';
        if ($hanyaAktif) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY kode';
        return Db::all($sql);
    }

    /** @return array<string,array<int,array<string,mixed>>> akun dikelompokkan per tipe */
    public static function akunPerTipe(): array
    {
        $groups = [];
        foreach (self::akunPosting() as $akun) {
            $groups[(string) $akun['tipe']][] = $akun;
        }
        return $groups;
    }

    /**
     * Filter dimensi untuk query buku besar.
     *
     * @param array<string,mixed> $filter
     * @return array{0:string,1:array<int,int>}
     */
    private static function dimensi(array $filter, string $alias = 'jl'): array
    {
        $sql = '';
        $params = [];
        foreach (['bidang_id', 'divisi_id', 'project_id', 'cost_center_id', 'partner_id'] as $key) {
            $value = (int) ($filter[$key] ?? 0);
            if ($value > 0) {
                $sql .= ' AND ' . $alias . '.' . $key . ' = ?';
                $params[] = $value;
            }
        }
        return [$sql, $params];
    }

    /**
     * Saldo sebelum tanggal (signed: debit positif).
     *
     * @param array<string,mixed> $filter
     */
    public static function saldoAwal(int $coaId, string $from, array $filter = []): float
    {
        [$dimensiSql, $params] = self::dimensi($filter);

        $value = Db::val(
            'SELECT COALESCE(SUM(jl.debit - jl.kredit), 0)
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             WHERE je.status = ? AND jl.coa_id = ? AND je.tanggal < ?' . $dimensiSql,
            array_merge(['posted', $coaId, $from], $params)
        );

        return (float) $value;
    }

    /**
     * Mutasi akun pada rentang tanggal.
     *
     * @param array<string,mixed> $filter
     * @return array<int,array<string,mixed>>
     */
    public static function mutasi(int $coaId, string $from, string $to, array $filter = []): array
    {
        [$dimensiSql, $params] = self::dimensi($filter);

        return Db::all(
            'SELECT jl.id, jl.debit, jl.kredit, jl.deskripsi AS keterangan, jl.urutan,
                    je.id AS journal_id, je.nomor, je.tanggal, je.tipe AS jurnal_tipe, je.deskripsi AS jurnal_deskripsi,
                    je.referensi, b.nama AS bidang_nama, d.nama AS divisi_nama,
                    pr.nama AS project_nama, cc.nama AS cost_center_nama, pt.nama AS partner_nama
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             LEFT JOIN ms_bidang b ON b.id = jl.bidang_id
             LEFT JOIN ms_divisi d ON d.id = jl.divisi_id
             LEFT JOIN ms_projects pr ON pr.id = jl.project_id
             LEFT JOIN ms_cost_centers cc ON cc.id = jl.cost_center_id
             LEFT JOIN ms_partners pt ON pt.id = jl.partner_id
             WHERE je.status = ? AND jl.coa_id = ? AND je.tanggal BETWEEN ? AND ?' . $dimensiSql . '
             ORDER BY je.tanggal, je.id, jl.urutan, jl.id',
            array_merge(['posted', $coaId, $from, $to], $params)
        );
    }

    /**
     * Neraca saldo: saldo awal (sebelum $from), mutasi periode, saldo akhir.
     *
     * @param array<string,mixed> $filter
     * @return array{rows:array<int,array<string,mixed>>,total_debit:float,total_kredit:float,total_awal:float,selisih:float,seimbang:bool}
     */
    public static function neracaSaldo(string $from, string $to, array $filter = [], ?string $tipe = null): array
    {
        [$dimensiSql, $params] = self::dimensi($filter);

        $sql = "SELECT c.id, c.kode, c.nama, c.tipe, c.saldo_normal,
                       COALESCE(SUM(CASE WHEN je.tanggal < ? THEN jl.debit ELSE 0 END), 0)  AS awal_debit,
                       COALESCE(SUM(CASE WHEN je.tanggal < ? THEN jl.kredit ELSE 0 END), 0) AS awal_kredit,
                       COALESCE(SUM(CASE WHEN je.tanggal BETWEEN ? AND ? THEN jl.debit ELSE 0 END), 0)  AS mutasi_debit,
                       COALESCE(SUM(CASE WHEN je.tanggal BETWEEN ? AND ? THEN jl.kredit ELSE 0 END), 0) AS mutasi_kredit
                FROM coa_accounts c
                LEFT JOIN journal_lines jl ON jl.coa_id = c.id
                LEFT JOIN journal_entries je ON je.id = jl.journal_id AND je.status = 'posted'
                WHERE c.allow_posting = 1";

        if ($tipe !== null && $tipe !== '') {
            $sql .= ' AND c.tipe = ?';
        }

        $sql .= ' GROUP BY c.id, c.kode, c.nama, c.tipe, c.saldo_normal ORDER BY c.kode';

        $params = array_merge([$from, $from, $from, $to, $from, $to], $params);
        if ($tipe !== null && $tipe !== '') {
            $params[] = $tipe;
        }

        $rows = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;
        $totalAwal = 0.0;

        foreach (Db::all($sql, $params) as $row) {
            $awal = (float) $row['awal_debit'] - (float) $row['awal_kredit'];
            $debit = (float) $row['mutasi_debit'];
            $kredit = (float) $row['mutasi_kredit'];
            $akhir = $awal + $debit - $kredit;

            if ($awal == 0.0 && $debit == 0.0 && $kredit == 0.0 && $akhir == 0.0) {
                continue;
            }

            $rows[] = [
                'id'            => (int) $row['id'],
                'kode'          => (string) $row['kode'],
                'nama'          => (string) $row['nama'],
                'tipe'          => (string) $row['tipe'],
                'saldo_normal'  => (string) $row['saldo_normal'],
                'awal'          => $awal,
                'mutasi_debit'  => $debit,
                'mutasi_kredit' => $kredit,
                'akhir'         => $akhir,
            ];

            $totalAwal += $awal;
            if ($akhir >= 0) {
                $totalDebit += $akhir;
            } else {
                $totalKredit += abs($akhir);
            }
        }

        $selisih = round($totalDebit - $totalKredit, 2);

        return [
            'rows'         => $rows,
            'total_debit'  => $totalDebit,
            'total_kredit' => $totalKredit,
            'total_awal'   => $totalAwal,
            'selisih'      => $selisih,
            'seimbang'     => abs($selisih) < 0.005,
        ];
    }

    /**
     * Rekap saldo akhir per tipe akun (untuk strip ringkasan).
     *
     * @param array<int,array<string,mixed>> $rows hasil neracaSaldo()['rows']
     * @return array<string,float>
     */
    public static function rekapTipe(array $rows): array
    {
        $rekap = [];
        foreach ($rows as $row) {
            $tipe = (string) $row['tipe'];
            $rekap[$tipe] = ($rekap[$tipe] ?? 0.0) + (float) $row['akhir'];
        }
        return $rekap;
    }

    /**
     * Ringkasan buku besar pembantu per dimensi (bidang/divisi/proyek/cost center/partner).
     *
     * @param array<int,array<string,mixed>> $rows hasil mutasi()
     * @param string $kolom kunci nama relasi, mis. bidang_nama
     * @return array<string,array{debit:float,kredit:float,saldo:float,baris:int}>
     */
    public static function rekapDimensi(array $rows, string $kolom): array
    {
        $rekap = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row[$kolom] ?? '')) ?: 'Tanpa dimensi';
            $rekap[$label] ??= ['debit' => 0.0, 'kredit' => 0.0, 'saldo' => 0.0, 'baris' => 0];
            $rekap[$label]['debit'] += (float) $row['debit'];
            $rekap[$label]['kredit'] += (float) $row['kredit'];
            $rekap[$label]['saldo'] += (float) $row['debit'] - (float) $row['kredit'];
            $rekap[$label]['baris']++;
        }

        uasort($rekap, static fn (array $a, array $b): int => abs($b['saldo']) <=> abs($a['saldo']));
        return $rekap;
    }

    /** Saldo akun per tanggal tertentu (signed). */
    public static function saldo(int $coaId, ?string $to = null): float
    {
        return (float) Db::val(
            'SELECT COALESCE(SUM(jl.debit - jl.kredit), 0)
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             WHERE je.status = ? AND jl.coa_id = ? AND je.tanggal <= ?',
            ['posted', $coaId, $to ?? today()]
        );
    }

    /** Saldo per tipe akun per tanggal (signed). */
    public static function saldoTipe(string $tipe, ?string $to = null): float
    {
        return (float) Db::val(
            'SELECT COALESCE(SUM(jl.debit - jl.kredit), 0)
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = ? AND c.tipe = ? AND je.tanggal <= ?',
            ['posted', $tipe, $to ?? today()]
        );
    }
}
