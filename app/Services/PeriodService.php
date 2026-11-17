<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Pengelolaan periode akuntansi (bulan & tahun buku).
 */
final class PeriodService
{
    /** @return array<string,mixed> */
    public static function current(): array
    {
        static $current = null;
        if ($current !== null) {
            return $current;
        }

        try {
            $row = Db::one(
                'SELECT * FROM fiscal_periods WHERE CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai ORDER BY tanggal_mulai DESC LIMIT 1'
            );
            if ($row === null) {
                $row = Db::one('SELECT * FROM fiscal_periods ORDER BY tanggal_mulai DESC LIMIT 1');
            }
        } catch (\Throwable) {
            $row = null;
        }

        $current = $row ?? [
            'id'              => 0,
            'tahun'           => (int) date('Y'),
            'bulan'           => (int) date('n'),
            'kode'            => date('Y-m'),
            'nama'            => bulan_nama((int) date('n')) . ' ' . date('Y'),
            'tanggal_mulai'   => date('Y-m-01'),
            'tanggal_selesai' => date('Y-m-t'),
            'status'          => 'open',
        ];

        return $current;
    }

    /** @return array<string,mixed>|null */
    public static function forDate(string $date): ?array
    {
        return Db::one(
            'SELECT * FROM fiscal_periods WHERE ? BETWEEN tanggal_mulai AND tanggal_selesai LIMIT 1',
            [$date]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function forYear(int $year): array
    {
        return Db::all('SELECT * FROM fiscal_periods WHERE tahun = ? ORDER BY bulan', [$year]);
    }

    /** @return array<string,mixed>|null */
    public static function fiscalYear(int $year): ?array
    {
        return Db::one('SELECT * FROM fiscal_years WHERE tahun = ?', [$year]);
    }

    /** @return array<int,int> */
    public static function years(): array
    {
        $rows = Db::all('SELECT DISTINCT tahun FROM fiscal_periods ORDER BY tahun DESC');
        $years = array_map(static fn ($r) => (int) $r['tahun'], $rows);
        return $years !== [] ? $years : [(int) date('Y')];
    }

    /**
     * Pastikan 12 periode + tahun buku tersedia (idempotent).
     */
    public static function ensureYear(int $year): void
    {
        $exists = Db::val('SELECT COUNT(*) FROM fiscal_years WHERE tahun = ?', [$year]);
        if ((int) $exists === 0) {
            Db::exec(
                'INSERT INTO fiscal_years (tahun, tanggal_mulai, tanggal_selesai, status, catatan)
                 VALUES (?, ?, ?, ?, ?)',
                [$year, $year . '-01-01', $year . '-12-31', 'open', 'Dibuat otomatis oleh sistem']
            );
        }

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $kode = sprintf('%d-%02d', $year, $bulan);
            $found = Db::val('SELECT COUNT(*) FROM fiscal_periods WHERE kode = ?', [$kode]);
            if ((int) $found > 0) {
                continue;
            }
            $start = sprintf('%s-%02d-01', $year, $bulan);
            Db::exec(
                'INSERT INTO fiscal_periods (tahun, bulan, kode, nama, tanggal_mulai, tanggal_selesai, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$year, $bulan, $kode, bulan_nama($bulan) . ' ' . $year, $start, date('Y-m-t', strtotime($start)), 'open']
            );
        }
    }

    public static function isOpen(int $periodId): bool
    {
        return (string) Db::val('SELECT status FROM fiscal_periods WHERE id = ?', [$periodId], 'open') === 'open';
    }
}
