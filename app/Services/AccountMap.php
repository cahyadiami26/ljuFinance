<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Pemetaan akun COA yang dipakai modul transaksi.
 *
 * Sumber utama tetap master terkait (rekening bank → coa_account_id,
 * tarif pajak → coa_masukan_id / coa_keluaran_id). Jika tidak tersedia,
 * dipakai pencarian berdasarkan kode akun.
 */
final class AccountMap
{
    /** @var array<string,int|null> */
    private static array $cache = [];

    public static function byKode(string $kode): ?int
    {
        $key = 'kode:' . $kode;
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = (int) (Db::val('SELECT id FROM coa_accounts WHERE kode = ? AND allow_posting = 1', [$kode]) ?? 0) ?: null;
        }
        return self::$cache[$key];
    }

    private static function firstPosting(string $prefix): ?int
    {
        $key = 'prefix:' . $prefix;
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = (int) (Db::val(
                'SELECT id FROM coa_accounts WHERE kode LIKE ? AND allow_posting = 1 AND is_active = 1 ORDER BY kode LIMIT 1',
                [$prefix . '%']
            ) ?? 0) ?: null;
        }
        return self::$cache[$key];
    }

    /** Akun COA milik rekening bank/kas internal. */
    public static function bankCoa(int $bankAccountId): int
    {
        $coaId = (int) Db::val('SELECT coa_account_id FROM ms_bank_accounts WHERE id = ?', [$bankAccountId]);
        if ($coaId <= 0) {
            throw new \RuntimeException('Rekening bank tidak memiliki akun COA yang terhubung.');
        }
        return $coaId;
    }

    /** Akun uang muka kegiatan (1-1410 → fallback 1-14xx). */
    public static function uangMuka(): int
    {
        $id = self::byKode('1-1410') ?? self::firstPosting('1-14');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk uang muka (1-1410) tidak ditemukan.');
        }
        return $id;
    }

    /** Akun PPN masukan (default dari tarif pajak PPN). */
    public static function ppnMasukan(?int $taxTypeId = null): int
    {
        if ($taxTypeId !== null) {
            $coa = (int) Db::val('SELECT coa_masukan_id FROM tax_types WHERE id = ?', [$taxTypeId]);
            if ($coa > 0) {
                return $coa;
            }
        }

        $coa = (int) Db::val("SELECT coa_masukan_id FROM tax_types WHERE jenis = 'ppn' ORDER BY id LIMIT 1");
        if ($coa > 0) {
            return $coa;
        }

        $id = self::byKode('1-1430') ?? self::firstPosting('1-143');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk PPN masukan tidak ditemukan.');
        }
        return $id;
    }

    /** Akun utang pajak untuk potongan (PPh). */
    public static function pajakPotongan(?int $taxTypeId = null): int
    {
        if ($taxTypeId !== null) {
            $coa = (int) Db::val('SELECT coa_keluaran_id FROM tax_types WHERE id = ?', [$taxTypeId]);
            if ($coa > 0) {
                return $coa;
            }
        }

        $coa = (int) Db::val("SELECT coa_keluaran_id FROM tax_types WHERE is_potongan = 1 AND coa_keluaran_id IS NOT NULL ORDER BY id LIMIT 1");
        if ($coa > 0) {
            return $coa;
        }

        $id = self::byKode('2-1200') ?? self::firstPosting('2-12');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk utang pajak tidak ditemukan.');
        }
        return $id;
    }
}
