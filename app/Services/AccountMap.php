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

    /** Akun piutang usaha (1-1310 → fallback 1-13xx). */
    public static function piutangUsaha(): int
    {
        $id = self::byKode('1-1310') ?? self::firstPosting('1-13');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk piutang usaha (1-1310) tidak ditemukan.');
        }
        return $id;
    }

    /** Akun pendapatan usaha utama (4-1100 → akun pendapatan posting pertama). */
    public static function pendapatanUsaha(): int
    {
        $id = self::byKode('4-1100');
        if ($id === null) {
            $id = (int) Db::val("SELECT id FROM coa_accounts WHERE allow_posting = 1 AND is_active = 1 AND tipe = 'pendapatan' ORDER BY kode LIMIT 1");
        }
        if ($id <= 0) {
            throw new \RuntimeException('Akun COA untuk pendapatan usaha tidak ditemukan.');
        }
        return $id;
    }

    /** Akun piutang belum ditagih untuk pengakuan pendapatan kontrak (1-1330). */
    public static function piutangBelumDitagih(): int
    {
        $id = self::byKode('1-1330') ?? self::byKode('1-1310');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk piutang belum ditagih (1-1330) tidak ditemukan.');
        }
        return $id;
    }

    /** Akun utang usaha vendor (2-1110 → fallback 2-11xx). */
    public static function utangUsaha(): int
    {
        $id = self::byKode('2-1110') ?? self::firstPosting('2-11');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk utang usaha (2-1110) tidak ditemukan.');
        }
        return $id;
    }

    /** Akun PPN keluaran (default dari tarif pajak PPN, fallback 2-1210). */
    public static function ppnKeluaran(?int $taxTypeId = null): int
    {
        if ($taxTypeId !== null) {
            $coa = (int) Db::val('SELECT coa_keluaran_id FROM tax_types WHERE id = ?', [$taxTypeId]);
            if ($coa > 0) {
                return $coa;
            }
        }

        $coa = (int) Db::val("SELECT coa_keluaran_id FROM tax_types WHERE jenis = 'ppn' ORDER BY id LIMIT 1");
        if ($coa > 0) {
            return $coa;
        }

        $id = self::byKode('2-1210') ?? self::firstPosting('2-12');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk PPN keluaran tidak ditemukan.');
        }
        return $id;
    }

    /** Akun kredit pajak PPh yang dipotong pelanggan (1-1440). */
    public static function pphDibayarDimuka(): int
    {
        $id = self::byKode('1-1440');
        if ($id === null) {
            throw new \RuntimeException('Akun COA untuk PPh dibayar dimuka (1-1440) tidak ditemukan.');
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

    /** Akun laba penjualan aset tetap (4-2200 → fallback 4-2xxx). */
    public static function labaPenjualanAset(): int
    {
        $id = self::byKode('4-2200') ?? self::firstPosting('4-2');
        if ($id === null) {
            throw new \RuntimeException('Akun COA laba penjualan aset (4-2200) tidak ditemukan.');
        }
        return $id;
    }

    /** Akun rugi penjualan/penghapusan aset (5-3900 → fallback 5-3xxx). */
    public static function rugiPenjualanAset(): int
    {
        $id = self::byKode('5-3900') ?? self::firstPosting('5-3');
        if ($id === null) {
            throw new \RuntimeException('Akun COA rugi penjualan aset (5-3900) tidak ditemukan.');
        }
        return $id;
    }
}
