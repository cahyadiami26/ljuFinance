<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Pengajuan biaya operasional (expense management).
 *
 * Alur: draft → diajukan → disetujui → dibayar (jurnal otomatis).
 *
 * Jurnal pembayaran:
 *   D Beban (sesuai COA tiap rincian)
 *   D PPN Masukan (jika ada)
 *   K Utang Pajak / PPh (jika ada potongan)
 *   K Bank (sebesar total bersih)
 */
final class ExpenseService
{
    public const TIPE = [
        'operasional'      => 'Operasional',
        'perjalanan_dinas' => 'Perjalanan Dinas',
        'rapat'            => 'Rapat',
        'pemeliharaan'     => 'Pemeliharaan',
        'transportasi'     => 'Transportasi',
        'lain'             => 'Lain-lain',
    ];

    public const STATUS = [
        'draft'     => 'Draft',
        'submitted' => 'Diajukan',
        'approved'  => 'Disetujui',
        'rejected'  => 'Ditolak',
        'dibayar'   => 'Dibayar',
        'void'      => 'Dibatalkan',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT e.*, u.name AS pemohon_nama, u.position AS pemohon_jabatan,
                    b.nama AS bank_nama, b.kode AS bank_kode,
                    bd.nama AS bidang_nama, dv.nama AS divisi_nama, pr.nama AS project_nama, cc.nama AS cost_center_nama,
                    pt.nama AS partner_nama, p.nama AS periode_nama, ap.name AS approved_by_name,
                    j.nomor AS journal_nomor
             FROM expense_requests e
             LEFT JOIN app_users u ON u.id = e.pemohon_user_id
             LEFT JOIN app_users ap ON ap.id = e.approved_by
             LEFT JOIN ms_bank_accounts b ON b.id = e.bank_account_id
             LEFT JOIN ms_bidang bd ON bd.id = e.bidang_id
             LEFT JOIN ms_divisi dv ON dv.id = e.divisi_id
             LEFT JOIN ms_projects pr ON pr.id = e.project_id
             LEFT JOIN ms_cost_centers cc ON cc.id = e.cost_center_id
             LEFT JOIN ms_partners pt ON pt.id = e.partner_id
             LEFT JOIN fiscal_periods p ON p.id = e.periode_id
             LEFT JOIN journal_entries j ON j.id = e.journal_id
             WHERE e.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $id): array
    {
        return Db::all(
            'SELECT i.*, c.kode AS coa_kode, c.nama AS coa_nama, t.nama AS pajak_nama, t.tarif AS pajak_tarif
             FROM expense_items i
             JOIN coa_accounts c ON c.id = i.coa_id
             LEFT JOIN tax_types t ON t.id = i.pajak_type_id
             WHERE i.expense_id = ?
             ORDER BY i.id',
            [$id]
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasi(array $input): array
    {
        $tipe = (string) ($input['tipe'] ?? 'operasional');

        return [
            'tanggal'         => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'tipe'            => array_key_exists($tipe, self::TIPE) ? $tipe : 'operasional',
            'judul'           => trim((string) ($input['judul'] ?? '')),
            'pemohon_user_id' => (int) ($input['pemohon_user_id'] ?? 0) ?: auth_id(),
            'bidang_id'       => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'       => (int) ($input['divisi_id'] ?? 0) ?: null,
            'cost_center_id'  => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'project_id'      => (int) ($input['project_id'] ?? 0) ?: null,
            'partner_id'      => (int) ($input['partner_id'] ?? 0) ?: null,
            'bank_account_id' => (int) ($input['bank_account_id'] ?? 0) ?: null,
            'keterangan'      => trim((string) ($input['keterangan'] ?? '')),
            'items'           => self::normalisasiItems($input['items'] ?? []),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    public static function normalisasiItems(array $items): array
    {
        $hasil = [];
        foreach ($items as $item) {
            $keterangan = trim((string) ($item['keterangan'] ?? ''));
            $coaId = (int) ($item['coa_id'] ?? 0);
            $jumlah = JournalService::uang($item['jumlah'] ?? 0);
            $ppn = JournalService::uang($item['ppn'] ?? 0);
            $pph = JournalService::uang($item['pph'] ?? 0);

            if ($keterangan === '' && $coaId <= 0 && $jumlah <= 0) {
                continue;
            }

            $hasil[] = [
                'keterangan'    => $keterangan,
                'coa_id'        => $coaId,
                'jumlah'        => $jumlah,
                'ppn'           => max(0.0, $ppn),
                'pph'           => max(0.0, $pph),
                'pajak_type_id' => (int) ($item['pajak_type_id'] ?? 0) ?: null,
                'no_bukti'      => trim((string) ($item['no_bukti'] ?? '')),
            ];
        }

        return $hasil;
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        if (strtotime((string) $data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal tidak valid.';
        } else {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                $errors['tanggal'] = 'Periode akuntansi belum tersedia untuk tanggal ini.';
            } elseif ((string) $periode['status'] !== 'open') {
                $errors['tanggal'] = 'Periode ' . $periode['nama'] . ' sudah ditutup/dikunci.';
            }
        }

        if ($data['judul'] === '') {
            $errors['judul'] = 'Judul pengajuan wajib diisi.';
        } elseif (mb_strlen((string) $data['judul']) > 150) {
            $errors['judul'] = 'Judul maksimal 150 karakter.';
        }

        if (empty($data['pemohon_user_id'])) {
            $errors['pemohon_user_id'] = 'Pemohon wajib dipilih.';
        }

        if ($data['items'] === []) {
            $errors['items'] = 'Minimal satu rincian biaya harus diisi.';
            return $errors;
        }

        $total = 0.0;
        foreach ($data['items'] as $index => $item) {
            $n = $index + 1;
            if ($item['keterangan'] === '') {
                $errors['items'] = 'Rincian #' . $n . ': keterangan wajib diisi.';
                break;
            }
            if ($item['coa_id'] <= 0) {
                $errors['items'] = 'Rincian #' . $n . ': akun COA wajib dipilih.';
                break;
            }
            if (!self::coaPosting($item['coa_id'])) {
                $errors['items'] = 'Rincian #' . $n . ': akun COA tidak boleh menerima posting.';
                break;
            }
            if ($item['jumlah'] <= 0) {
                $errors['items'] = 'Rincian #' . $n . ': jumlah harus lebih besar dari nol.';
                break;
            }
            if ($item['ppn'] < 0 || $item['pph'] < 0) {
                $errors['items'] = 'Rincian #' . $n . ': nilai pajak tidak boleh negatif.';
                break;
            }
            if ($item['pph'] > $item['jumlah']) {
                $errors['items'] = 'Rincian #' . $n . ': potongan PPh melebihi nilai biaya.';
                break;
            }
            $total += $item['jumlah'] + $item['ppn'] - $item['pph'];
        }

        if (!isset($errors['items']) && $total <= 0) {
            $errors['items'] = 'Total pengajuan harus lebih besar dari nol.';
        }

        return $errors;
    }

    private static function coaPosting(int $coaId): bool
    {
        return (int) Db::val('SELECT COUNT(*) FROM coa_accounts WHERE id = ? AND allow_posting = 1 AND is_active = 1', [$coaId]) > 0;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{subtotal:float,ppn:float,pph:float,total:float}
     */
    public static function hitung(array $items): array
    {
        $subtotal = 0.0;
        $ppn = 0.0;
        $pph = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['jumlah'];
            $ppn += (float) $item['ppn'];
            $pph += (float) $item['pph'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'ppn'      => round($ppn, 2),
            'pph'      => round($pph, 2),
            'total'    => round($subtotal + $ppn - $pph, 2),
        ];
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi tidak tersedia.');
            }

            $total = self::hitung($data['items']);

            $payload = [
                'tanggal'         => $data['tanggal'],
                'periode_id'      => (int) $periode['id'],
                'tipe'            => $data['tipe'],
                'judul'           => $data['judul'],
                'pemohon_user_id' => (int) $data['pemohon_user_id'],
                'bidang_id'       => $data['bidang_id'],
                'divisi_id'       => $data['divisi_id'],
                'cost_center_id'  => $data['cost_center_id'],
                'project_id'      => $data['project_id'],
                'partner_id'      => $data['partner_id'],
                'bank_account_id' => $data['bank_account_id'],
                'subtotal'        => number_format($total['subtotal'], 2, '.', ''),
                'ppn'             => number_format($total['ppn'], 2, '.', ''),
                'pph'             => number_format($total['pph'], 2, '.', ''),
                'total'           => number_format($total['total'], 2, '.', ''),
                'keterangan'      => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next('EXP', (string) $data['tanggal']);
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('expense_requests', $payload);
            } else {
                Db::update('expense_requests', $payload, 'id = :id', [':id' => $id]);
            }

            Db::exec('DELETE FROM expense_items WHERE expense_id = ?', [$id]);
            foreach ($data['items'] as $item) {
                Db::insert('expense_items', [
                    'expense_id'    => $id,
                    'keterangan'    => mb_substr((string) $item['keterangan'], 0, 255),
                    'coa_id'        => (int) $item['coa_id'],
                    'jumlah'        => number_format((float) $item['jumlah'], 2, '.', ''),
                    'ppn'           => number_format((float) $item['ppn'], 2, '.', ''),
                    'pph'           => number_format((float) $item['pph'], 2, '.', ''),
                    'pajak_type_id' => $item['pajak_type_id'],
                    'no_bukti'      => $item['no_bukti'] !== '' ? mb_substr((string) $item['no_bukti'], 0, 80) : null,
                ]);
            }

            return $id;
        });
    }

    public static function ajukan(int $id): void
    {
        $row = Db::one('SELECT status FROM expense_requests WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Pengajuan biaya tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Hanya draft yang dapat diajukan.');
        }
        Db::exec('UPDATE expense_requests SET status = ? WHERE id = ?', ['submitted', $id]);
    }

    public static function setujui(int $id): void
    {
        $row = Db::one('SELECT status FROM expense_requests WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Pengajuan biaya tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya pengajuan yang dapat disetujui.');
        }
        Db::exec('UPDATE expense_requests SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?', [
            'approved', auth_id() ?: null, now(), $id,
        ]);
    }

    public static function tolak(int $id, string $alasan = ''): void
    {
        $row = Db::one('SELECT nomor, status FROM expense_requests WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Pengajuan biaya tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya pengajuan yang dapat ditolak.');
        }
        Db::exec('UPDATE expense_requests SET status = ? WHERE id = ?', ['rejected', $id]);

        Audit::log('reject', 'expense', [
            'ref_table'   => 'expense_requests',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tolak pengajuan biaya: ' . ($alasan !== '' ? $alasan : 'tanpa alasan'),
        ]);
    }

    /**
     * Pembayaran biaya: D Beban + D PPN Masukan / K PPh + K Bank.
     */
    public static function bayar(int $id, int $bankAccountId, string $tanggal): int
    {
        return Db::transaction(static function () use ($id, $bankAccountId, $tanggal): int {
            $row = Db::one('SELECT * FROM expense_requests WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Pengajuan biaya tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new \RuntimeException('Pengajuan biaya harus disetujui sebelum dibayar.');
            }

            $items = self::items($id);
            if ($items === []) {
                throw new \RuntimeException('Rincian biaya belum diisi.');
            }

            $periode = PeriodService::forDate($tanggal);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode tanggal pembayaran tidak terbuka.');
            }

            $bank = Db::one('SELECT * FROM ms_bank_accounts WHERE id = ?', [$bankAccountId]);
            if ($bank === null || (int) $bank['is_active'] !== 1) {
                throw new \RuntimeException('Rekening bank sumber tidak aktif.');
            }

            $total = (float) $row['total'];
            $lines = [];
            $ppnPerAkun = [];
            $pphPerAkun = [];

            foreach ($items as $item) {
                $lines[] = [
                    'coa_id'         => (int) $item['coa_id'],
                    'deskripsi'      => (string) $item['keterangan'],
                    'debit'          => (float) $item['jumlah'],
                    'kredit'         => 0.0,
                    'bidang_id'      => $row['bidang_id'] ?: null,
                    'divisi_id'      => $row['divisi_id'] ?: null,
                    'project_id'     => $row['project_id'] ?: null,
                    'cost_center_id' => $row['cost_center_id'] ?: null,
                    'partner_id'     => $row['partner_id'] ?: null,
                    'tax_type_id'    => $item['pajak_type_id'] ?: null,
                ];

                if ((float) $item['ppn'] > 0) {
                    $akun = AccountMap::ppnMasukan($item['pajak_type_id'] ? (int) $item['pajak_type_id'] : null);
                    $ppnPerAkun[$akun] = ($ppnPerAkun[$akun] ?? 0.0) + (float) $item['ppn'];
                }

                if ((float) $item['pph'] > 0) {
                    $akun = AccountMap::pajakPotongan($item['pajak_type_id'] ? (int) $item['pajak_type_id'] : null);
                    $pphPerAkun[$akun] = ($pphPerAkun[$akun] ?? 0.0) + (float) $item['pph'];
                }
            }

            foreach ($ppnPerAkun as $akun => $nilai) {
                $lines[] = [
                    'coa_id'    => (int) $akun,
                    'deskripsi' => 'PPN Masukan ' . $row['nomor'],
                    'debit'     => round($nilai, 2),
                    'kredit'    => 0.0,
                ];
            }

            foreach ($pphPerAkun as $akun => $nilai) {
                $lines[] = [
                    'coa_id'    => (int) $akun,
                    'deskripsi' => 'Potongan PPh ' . $row['nomor'],
                    'debit'     => 0.0,
                    'kredit'    => round($nilai, 2),
                ];
            }

            $lines[] = [
                'coa_id'    => AccountMap::bankCoa($bankAccountId),
                'deskripsi' => 'Pembayaran biaya ' . $row['nomor'],
                'debit'     => 0.0,
                'kredit'    => $total,
            ];

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'pengeluaran',
                'sumber'       => 'biaya',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Pembayaran biaya — ' . $row['judul'],
                'kode_dokumen' => 'KK',
            ], $lines);

            Db::exec('UPDATE expense_requests SET status = ?, bank_account_id = ?, journal_id = ? WHERE id = ?', [
                'dibayar', $bankAccountId, $journalId, $id,
            ]);

            Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan - ? WHERE id = ?', [
                number_format($total, 2, '.', ''), $bankAccountId,
            ]);

            Audit::log('pay', 'expense', [
                'ref_table'   => 'expense_requests',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Pembayaran biaya ' . $row['nomor'],
                'new'         => ['total' => $total, 'journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    public static function hapus(int $id): void
    {
        $row = Db::one('SELECT status FROM expense_requests WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Pengajuan biaya tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Pengajuan yang sudah diproses tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM expense_requests WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed> */
    public static function ringkasan(int $tahun): array
    {
        $row = Db::one(
            'SELECT COUNT(*) AS jumlah,
                    SUM(total) AS total,
                    SUM(CASE WHEN status = ? THEN total ELSE 0 END) AS dibayar,
                    SUM(CASE WHEN status IN (?, ?) THEN total ELSE 0 END) AS menunggu
             FROM expense_requests
             WHERE YEAR(tanggal) = ?',
            ['dibayar', 'submitted', 'approved', $tahun]
        );

        return [
            'jumlah'   => (int) ($row['jumlah'] ?? 0),
            'total'    => (float) ($row['total'] ?? 0),
            'dibayar'  => (float) ($row['dibayar'] ?? 0),
            'menunggu' => (float) ($row['menunggu'] ?? 0),
        ];
    }
}
