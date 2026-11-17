<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Penomoran dokumen otomatis (unik per kode + periode).
 * Dipakai oleh seluruh modul transaksi (tahap 2 ke atas).
 */
final class NumberingService
{
    /** Prefix bawaan bila kode belum terdaftar di doc_sequences. */
    private const DEFAULTS = [
        'JU'   => 'JU/%m/%Y/',
        'KK'   => 'KK/%m/%Y/',
        'KM'   => 'KM/%m/%Y/',
        'TRF'  => 'TRF/%m/%Y/',
        'CA'   => 'CA/%m/%Y/',
        'EXP'  => 'EXP/%m/%Y/',
        'AP'   => 'AP/%m/%Y/',
        'APP'  => 'APP/%Y/',
        'AR'   => 'AR/%m/%Y/',
        'ARP'  => 'ARP/%Y/',
        'AST'  => 'AST/%Y/',
        'DEP'  => 'DEP/%m/%Y/',
        'TAX'  => 'TAX/%m/%Y/',
        'RKAP' => 'RKAP/%Y/',
    ];

    /**
     * Ambil nomor berikutnya. WAJIB dipanggil di dalam Db::transaction().
     */
    public static function next(string $code, ?string $date = null): string
    {
        $date ??= date('Y-m-d');
        $year = date('Y', strtotime($date));
        $periodKey = $year;

        $row = Db::one(
            'SELECT * FROM doc_sequences WHERE code = ? AND period_key = ? FOR UPDATE',
            [$code, $periodKey]
        );

        if ($row === null) {
            $prefix = strtr(self::DEFAULTS[$code] ?? ($code . '/%Y/'), [
                '%Y' => $year,
                '%m' => date('m', strtotime($date)),
                '%d' => date('d', strtotime($date)),
            ]);
            Db::exec(
                'INSERT INTO doc_sequences (code, period_key, prefix, last_number, padding) VALUES (?, ?, ?, 0, 4)
                 ON DUPLICATE KEY UPDATE prefix = VALUES(prefix)',
                [$code, $periodKey, $prefix]
            );
            $row = Db::one('SELECT * FROM doc_sequences WHERE code = ? AND period_key = ? FOR UPDATE', [$code, $periodKey]);
        }

        $prefix = strtr((string) ($row['prefix'] ?? ''), [
            '%Y' => $year,
            '%m' => date('m', strtotime($date)),
            '%d' => date('d', strtotime($date)),
        ]);

        $next = ((int) ($row['last_number'] ?? 0)) + 1;
        $padding = max(1, (int) ($row['padding'] ?? 4));

        Db::exec(
            'UPDATE doc_sequences SET last_number = ? WHERE code = ? AND period_key = ?',
            [$next, $code, $periodKey]
        );

        return $prefix . str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    /** Nomor sementara (preview tanpa increment). */
    public static function preview(string $code, ?string $date = null): string
    {
        $date ??= date('Y-m-d');
        $year = date('Y', strtotime($date));
        $row = Db::one('SELECT * FROM doc_sequences WHERE code = ? AND period_key = ?', [$code, $year]);
        $prefix = strtr((string) ($row['prefix'] ?? (self::DEFAULTS[$code] ?? $code . '/')), [
            '%Y' => $year,
            '%m' => date('m', strtotime($date)),
            '%d' => date('d', strtotime($date)),
        ]);
        $next = ((int) ($row['last_number'] ?? 0)) + 1;
        return $prefix . str_pad((string) $next, (int) ($row['padding'] ?? 4), '0', STR_PAD_LEFT);
    }
}
