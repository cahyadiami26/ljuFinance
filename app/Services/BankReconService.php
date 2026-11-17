<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Rekonsiliasi bank.
 *
 * Rekonsiliasi membandingkan saldo menurut buku besar (jurnal pada akun COA
 * rekening) dengan saldo menurut rekening koran. Baris buku diambil otomatis
 * dari jurnal periode terkait; baris bank diisi manual dari rekening koran.
 */
final class BankReconService
{
    public const STATUS = [
        'draft'  => 'Draft',
        'selesai' => 'Selesai',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT r.*, b.nama AS bank_nama, b.kode AS bank_kode, b.nomor_rekening, b.nama_bank,
                    p.nama AS periode_nama, u.name AS created_by_name
             FROM bank_reconciliations r
             JOIN ms_bank_accounts b ON b.id = r.bank_account_id
             LEFT JOIN fiscal_periods p ON p.id = r.periode_id
             LEFT JOIN app_users u ON u.id = r.created_by
             WHERE r.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $id): array
    {
        return Db::all(
            'SELECT * FROM bank_reconciliation_items WHERE reconciliation_id = ? ORDER BY sumber DESC, tanggal, id',
            [$id]
        );
    }

    /**
     * Baris buku besar pada akun COA rekening untuk rentang tanggal.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function barisBuku(int $bankAccountId, string $dari, string $sampai): array
    {
        $coaId = AccountMap::bankCoa($bankAccountId);

        return Db::all(
            "SELECT j.tanggal, j.nomor AS referensi, COALESCE(l.deskripsi, j.deskripsi) AS keterangan,
                    l.debit, l.kredit
             FROM journal_lines l
             JOIN journal_entries j ON j.id = l.journal_id
             WHERE l.coa_id = ? AND j.status = 'posted' AND j.tanggal BETWEEN ? AND ?
             ORDER BY j.tanggal, j.id, l.urutan",
            [$coaId, $dari, $sampai]
        );
    }

    /** Saldo buku (debit − kredit) akun rekening sampai tanggal tertentu. */
    public static function saldoBuku(int $bankAccountId, string $sampai): float
    {
        $coaId = AccountMap::bankCoa($bankAccountId);

        return (float) Db::val(
            "SELECT COALESCE(SUM(l.debit - l.kredit), 0)
             FROM journal_lines l
             JOIN journal_entries j ON j.id = l.journal_id
             WHERE l.coa_id = ? AND j.status = 'posted' AND j.tanggal <= ?",
            [$coaId, $sampai],
            0.0
        );
    }

    /**
     * Mutasi buku dalam rentang tanggal.
     *
     * @return array{masuk:float,keluar:float,net:float}
     */
    public static function mutasiBuku(int $bankAccountId, string $dari, string $sampai): array
    {
        $row = Db::one(
            "SELECT COALESCE(SUM(l.debit), 0) AS masuk, COALESCE(SUM(l.kredit), 0) AS keluar
             FROM journal_lines l
             JOIN journal_entries j ON j.id = l.journal_id
             WHERE l.coa_id = ? AND j.status = 'posted' AND j.tanggal BETWEEN ? AND ?",
            [AccountMap::bankCoa($bankAccountId), $dari, $sampai]
        );

        $masuk = (float) ($row['masuk'] ?? 0);
        $keluar = (float) ($row['keluar'] ?? 0);

        return ['masuk' => $masuk, 'keluar' => $keluar, 'net' => $masuk - $keluar];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalisasi(array $input): array
    {
        $items = [];
        foreach ((array) ($input['items'] ?? []) as $item) {
            $keterangan = trim((string) ($item['keterangan'] ?? ''));
            $debit = JournalService::uang($item['debit'] ?? 0);
            $kredit = JournalService::uang($item['kredit'] ?? 0);
            if ($keterangan === '' && $debit <= 0 && $kredit <= 0) {
                continue;
            }
            $items[] = [
                'tanggal'    => trim((string) ($item['tanggal'] ?? '')) ?: null,
                'referensi'  => trim((string) ($item['referensi'] ?? '')),
                'keterangan' => $keterangan,
                'debit'      => $debit,
                'kredit'     => $kredit,
                'sumber'     => (string) ($item['sumber'] ?? 'bank') === 'buku' ? 'buku' : 'bank',
                'is_matched' => !empty($item['is_matched']) ? 1 : 0,
            ];
        }

        return [
            'bank_account_id' => (int) ($input['bank_account_id'] ?? 0),
            'tanggal_mulai'   => trim((string) ($input['tanggal_mulai'] ?? '')) ?: today(),
            'tanggal_akhir'   => trim((string) ($input['tanggal_akhir'] ?? '')) ?: today(),
            'saldo_bank'      => JournalService::uang($input['saldo_bank'] ?? 0),
            'catatan'         => trim((string) ($input['catatan'] ?? '')),
            'items'           => $items,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        $bank = Db::one('SELECT id, is_active FROM ms_bank_accounts WHERE id = ?', [(int) $data['bank_account_id']]);
        if ($bank === null) {
            $errors['bank_account_id'] = 'Rekening bank wajib dipilih.';
        } elseif ((int) $bank['is_active'] !== 1) {
            $errors['bank_account_id'] = 'Rekening bank tidak aktif.';
        }

        if (strtotime((string) $data['tanggal_mulai']) === false || strtotime((string) $data['tanggal_akhir']) === false) {
            $errors['tanggal_akhir'] = 'Rentang tanggal tidak valid.';
        } elseif ($data['tanggal_mulai'] > $data['tanggal_akhir']) {
            $errors['tanggal_akhir'] = 'Tanggal akhir harus setelah tanggal mulai.';
        } else {
            $periode = PeriodService::forDate((string) $data['tanggal_akhir']);
            if ($periode === null) {
                $errors['tanggal_akhir'] = 'Periode akuntansi tidak tersedia.';
            }
        }

        return $errors;
    }

    /**
     * Buat rekonsiliasi baru beserta baris buku otomatis.
     *
     * @param array<string,mixed> $data
     */
    public static function simpan(array $data): int
    {
        return Db::transaction(static function () use ($data): int {
            $periode = PeriodService::forDate((string) $data['tanggal_akhir']);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi tidak tersedia.');
            }

            $saldoBuku = self::saldoBuku((int) $data['bank_account_id'], (string) $data['tanggal_akhir']);
            $saldoBank = (float) $data['saldo_bank'];

            $reconId = Db::insert('bank_reconciliations', [
                'bank_account_id' => (int) $data['bank_account_id'],
                'periode_id'      => (int) $periode['id'],
                'tanggal_mulai'   => $data['tanggal_mulai'],
                'tanggal_akhir'   => $data['tanggal_akhir'],
                'saldo_bank'      => number_format($saldoBank, 2, '.', ''),
                'saldo_buku'      => number_format($saldoBuku, 2, '.', ''),
                'selisih'         => number_format($saldoBank - $saldoBuku, 2, '.', ''),
                'catatan'         => $data['catatan'] !== '' ? mb_substr((string) $data['catatan'], 0, 255) : null,
                'created_by'      => auth_id() ?: null,
                'status'          => 'draft',
            ]);

            foreach (self::barisBuku((int) $data['bank_account_id'], (string) $data['tanggal_mulai'], (string) $data['tanggal_akhir']) as $row) {
                Db::insert('bank_reconciliation_items', [
                    'reconciliation_id' => $reconId,
                    'tanggal'           => $row['tanggal'],
                    'referensi'         => mb_substr((string) $row['referensi'], 0, 80),
                    'keterangan'        => mb_substr((string) $row['keterangan'], 0, 255),
                    'debit'             => number_format((float) $row['debit'], 2, '.', ''),
                    'kredit'            => number_format((float) $row['kredit'], 2, '.', ''),
                    'sumber'            => 'buku',
                    'is_matched'        => 0,
                ]);
            }

            foreach ($data['items'] as $item) {
                Db::insert('bank_reconciliation_items', [
                    'reconciliation_id' => $reconId,
                    'tanggal'           => $item['tanggal'],
                    'referensi'         => $item['referensi'] !== '' ? mb_substr((string) $item['referensi'], 0, 80) : null,
                    'keterangan'        => $item['keterangan'] !== '' ? mb_substr((string) $item['keterangan'], 0, 255) : null,
                    'debit'             => number_format((float) $item['debit'], 2, '.', ''),
                    'kredit'            => number_format((float) $item['kredit'], 2, '.', ''),
                    'sumber'            => $item['sumber'],
                    'is_matched'        => $item['is_matched'],
                ]);
            }

            self::hitungUlang($reconId);

            Audit::log('create', 'reconciliation', [
                'ref_table'   => 'bank_reconciliations',
                'ref_id'      => $reconId,
                'ref_code'    => 'RECON-' . $reconId,
                'description' => 'Rekonsiliasi bank periode ' . $periode['nama'],
                'new'         => ['saldo_buku' => $saldoBuku, 'saldo_bank' => $saldoBank],
            ]);

            return $reconId;
        });
    }

    /**
     * Tandai baris cocok lalu hitung ulang rekap.
     *
     * @param array<int,int|string> $matchedIds
     */
    public static function cocokkan(int $reconId, array $matchedIds): void
    {
        Db::transaction(static function () use ($reconId, $matchedIds): void {
            $row = Db::one('SELECT status FROM bank_reconciliations WHERE id = ?', [$reconId]);
            if ($row === null) {
                throw new \RuntimeException('Rekonsiliasi tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'draft') {
                throw new \RuntimeException('Rekonsiliasi yang sudah selesai tidak dapat diubah.');
            }

            $ids = array_values(array_filter(array_map('intval', $matchedIds), static fn (int $v): bool => $v > 0));
            Db::exec('UPDATE bank_reconciliation_items SET is_matched = 0 WHERE reconciliation_id = ?', [$reconId]);
            if ($ids !== []) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                Db::exec(
                    "UPDATE bank_reconciliation_items SET is_matched = 1 WHERE reconciliation_id = ? AND id IN ($ph)",
                    array_merge([$reconId], $ids)
                );
            }

            self::hitungUlang($reconId);
        });
    }

    public static function hitungUlang(int $reconId): void
    {
        $row = Db::one('SELECT * FROM bank_reconciliations WHERE id = ?', [$reconId]);
        if ($row === null) {
            return;
        }

        $cocok = (int) Db::val('SELECT COUNT(*) FROM bank_reconciliation_items WHERE reconciliation_id = ? AND is_matched = 1', [$reconId], 0);
        $total = (int) Db::val('SELECT COUNT(*) FROM bank_reconciliation_items WHERE reconciliation_id = ?', [$reconId], 0);

        $saldoBuku = self::saldoBuku((int) $row['bank_account_id'], (string) $row['tanggal_akhir']);

        Db::exec(
            'UPDATE bank_reconciliations SET saldo_buku = ?, selisih = saldo_bank - ?, jumlah_cocok = ?, jumlah_selisih = ? WHERE id = ?',
            [number_format($saldoBuku, 2, '.', ''), number_format($saldoBuku, 2, '.', ''), $cocok, max(0, $total - $cocok), $reconId]
        );

    }

    public static function selesaikan(int $reconId, string $catatan = ''): void
    {
        Db::transaction(static function () use ($reconId, $catatan): void {
            $row = Db::one('SELECT * FROM bank_reconciliations WHERE id = ?', [$reconId]);
            if ($row === null) {
                throw new \RuntimeException('Rekonsiliasi tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'draft') {
                throw new \RuntimeException('Rekonsiliasi sudah diselesaikan.');
            }

            self::hitungUlang($reconId);

            $fresh = Db::one('SELECT selisih, catatan FROM bank_reconciliations WHERE id = ?', [$reconId]);
            if ($fresh !== null && abs((float) $fresh['selisih']) > 0.005 && $catatan === '') {
                throw new \RuntimeException('Masih ada selisih saldo. Isi catatan penjelasan sebelum menyelesaikan.');
            }

            Db::exec('UPDATE bank_reconciliations SET status = ?, catatan = ? WHERE id = ?', [
                'selesai',
                $catatan !== '' ? mb_substr($catatan, 0, 255) : ($fresh['catatan'] ?? $row['catatan']),
                $reconId,
            ]);

            Audit::log('update', 'reconciliation', [
                'ref_table'   => 'bank_reconciliations',
                'ref_id'      => $reconId,
                'ref_code'    => 'RECON-' . $reconId,
                'description' => 'Rekonsiliasi bank diselesaikan',
            ]);
        });
    }

    public static function hapus(int $reconId): void
    {
        $row = Db::one('SELECT status FROM bank_reconciliations WHERE id = ?', [$reconId]);
        if ($row === null) {
            throw new \RuntimeException('Rekonsiliasi tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            throw new \RuntimeException('Rekonsiliasi yang sudah selesai tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM bank_reconciliations WHERE id = ?', [$reconId]);
    }
}
