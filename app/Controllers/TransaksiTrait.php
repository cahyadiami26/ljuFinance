<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Db;

/**
 * Opsi master data yang dipakai form transaksi kas, uang muka, dan biaya.
 */
trait TransaksiTrait
{
    /** @return array<int,array<string,mixed>> */
    protected function opsiBank(): array
    {
        return Db::all(
            'SELECT b.id, b.kode, b.nama, b.nama_bank, b.nomor_rekening, b.saldo_berjalan, b.tipe,
                    c.kode AS coa_kode, c.nama AS coa_nama
             FROM ms_bank_accounts b
             LEFT JOIN coa_accounts c ON c.id = b.coa_account_id
             WHERE b.is_active = 1
             ORDER BY b.tipe, b.kode'
        );
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiUser(): array
    {
        return Db::all(
            "SELECT id, name, position FROM app_users WHERE is_active = 1 ORDER BY name"
        );
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiBidang(): array
    {
        return Db::all('SELECT id, kode, nama FROM ms_bidang WHERE is_active = 1 ORDER BY nama');
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiDivisi(): array
    {
        return Db::all('SELECT id, kode, nama FROM ms_divisi WHERE is_active = 1 ORDER BY nama');
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiCostCenter(): array
    {
        return Db::all('SELECT id, kode, nama FROM ms_cost_centers WHERE is_active = 1 ORDER BY kode');
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiProject(): array
    {
        return Db::all('SELECT id, kode, nama FROM ms_projects WHERE is_active = 1 ORDER BY kode');
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiPartner(): array
    {
        return Db::all('SELECT id, kode, nama, tipe FROM ms_partners WHERE is_active = 1 ORDER BY nama');
    }

    /** Akun COA yang boleh menerima posting (untuk rincian biaya). */
    protected function opsiCoaPosting(): array
    {
        return Db::all(
            'SELECT id, kode, nama, tipe FROM coa_accounts
             WHERE allow_posting = 1 AND is_active = 1
             ORDER BY kode'
        );
    }

    /** @return array<int,array<string,mixed>> */
    protected function opsiPajak(): array
    {
        return Db::all('SELECT id, kode, nama, tarif, jenis FROM tax_types WHERE is_active = 1 ORDER BY kode');
    }

    /** Pasangan kode → nama untuk tampilan badge tipe transaksi. */
    protected function labelStatus(string $status, array $map): string
    {
        return (string) ($map[$status] ?? ucfirst($status));
    }
}
