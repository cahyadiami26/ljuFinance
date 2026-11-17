<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Transaksi kas & bank (masuk, keluar, transfer antar rekening).
 *
 * Posting membuat jurnal otomatis dan menyesuaikan saldo rekening:
 *   masuk    : D Bank,      K Akun lawan
 *   keluar   : D Akun lawan, K Bank
 *   transfer : D Bank tujuan, K Bank asal
 */
final class CashService
{
    public const TIPE = [
        'masuk'    => 'Kas Masuk',
        'keluar'   => 'Kas Keluar',
        'transfer' => 'Transfer Antar Rekening',
    ];

    public const STATUS = [
        'draft'     => 'Draft',
        'submitted' => 'Diajukan',
        'approved'  => 'Disetujui',
        'posted'    => 'Posted',
        'rejected'  => 'Ditolak',
        'void'      => 'Dibatalkan',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT t.*, b.nama AS bank_nama, b.kode AS bank_kode, b.tipe AS bank_tipe, b.mata_uang,
                    b2.nama AS bank_tujuan_nama, b2.kode AS bank_tujuan_kode,
                    c.kode AS coa_kode, c.nama AS coa_nama, pt.nama AS partner_nama,
                    p.nama AS periode_nama, p.status AS periode_status, u.name AS created_by_name, a.name AS approved_by_name,
                    j.nomor AS journal_nomor, j.status AS journal_status
             FROM cash_transactions t
             LEFT JOIN ms_bank_accounts b ON b.id = t.bank_account_id
             LEFT JOIN ms_bank_accounts b2 ON b2.id = t.bank_account_tujuan_id
             LEFT JOIN coa_accounts c ON c.id = t.coa_lawan_id
             LEFT JOIN ms_partners pt ON pt.id = t.partner_id
             LEFT JOIN fiscal_periods p ON p.id = t.periode_id
             LEFT JOIN app_users u ON u.id = t.created_by
             LEFT JOIN app_users a ON a.id = t.approved_by
             LEFT JOIN journal_entries j ON j.id = t.journal_id
             WHERE t.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalisasi(array $input): array
    {
        return [
            'tanggal'                 => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'tipe'                    => (string) ($input['tipe'] ?? 'keluar'),
            'bank_account_id'         => (int) ($input['bank_account_id'] ?? 0),
            'bank_account_tujuan_id'  => (int) ($input['bank_account_tujuan_id'] ?? 0) ?: null,
            'coa_lawan_id'            => (int) ($input['coa_lawan_id'] ?? 0),
            'partner_id'              => (int) ($input['partner_id'] ?? 0) ?: null,
            'project_id'              => (int) ($input['project_id'] ?? 0) ?: null,
            'bidang_id'               => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'               => (int) ($input['divisi_id'] ?? 0) ?: null,
            'cost_center_id'          => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'jumlah'                  => JournalService::uang($input['jumlah'] ?? 0),
            'referensi'               => trim((string) ($input['referensi'] ?? '')),
            'deskripsi'               => trim((string) ($input['deskripsi'] ?? '')),
        ];
    }

    /** @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        if (!array_key_exists($data['tipe'], self::TIPE)) {
            $errors['tipe'] = 'Tipe transaksi tidak dikenal.';
        }

        if (strtotime((string) $data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal tidak valid.';
        } else {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                $errors['tanggal'] = 'Belum ada periode akuntansi untuk tanggal ini.';
            } elseif ((string) $periode['status'] !== 'open') {
                $errors['tanggal'] = 'Periode ' . $periode['nama'] . ' sudah ditutup/dikunci.';
            }
        }

        $bank = $data['bank_account_id'] > 0
            ? Db::one('SELECT * FROM ms_bank_accounts WHERE id = ?', [$data['bank_account_id']])
            : null;
        if ($bank === null) {
            $errors['bank_account_id'] = 'Rekening kas/bank wajib dipilih.';
        } elseif ((int) $bank['is_active'] !== 1) {
            $errors['bank_account_id'] = 'Rekening tidak aktif.';
        }

        if ($data['tipe'] === 'transfer') {
            if (empty($data['bank_account_tujuan_id'])) {
                $errors['bank_account_tujuan_id'] = 'Rekening tujuan wajib dipilih.';
            } elseif ((int) $data['bank_account_tujuan_id'] === (int) $data['bank_account_id']) {
                $errors['bank_account_tujuan_id'] = 'Rekening tujuan tidak boleh sama dengan rekening asal.';
            } else {
                $tujuan = Db::one('SELECT is_active FROM ms_bank_accounts WHERE id = ?', [(int) $data['bank_account_tujuan_id']]);
                if ($tujuan === null || (int) $tujuan['is_active'] !== 1) {
                    $errors['bank_account_tujuan_id'] = 'Rekening tujuan tidak ditemukan / tidak aktif.';
                }
            }
        } else {
            if ((int) $data['coa_lawan_id'] <= 0) {
                $errors['coa_lawan_id'] = 'Akun lawan wajib dipilih.';
            } else {
                $akun = Db::one('SELECT allow_posting, is_active, kode FROM coa_accounts WHERE id = ?', [(int) $data['coa_lawan_id']]);
                if ($akun === null) {
                    $errors['coa_lawan_id'] = 'Akun lawan tidak ditemukan.';
                } elseif ((int) $akun['allow_posting'] !== 1) {
                    $errors['coa_lawan_id'] = 'Akun ' . $akun['kode'] . ' adalah akun induk.';
                } elseif ((int) $akun['is_active'] !== 1) {
                    $errors['coa_lawan_id'] = 'Akun lawan tidak aktif.';
                }
            }
        }

        if ($data['jumlah'] <= 0) {
            $errors['jumlah'] = 'Jumlah harus lebih besar dari nol.';
        }

        if ($data['deskripsi'] === '') {
            $errors['deskripsi'] = 'Deskripsi wajib diisi.';
        }

        if (mb_strlen((string) $data['referensi']) > 80) {
            $errors['referensi'] = 'Referensi maksimal 80 karakter.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi tidak tersedia untuk tanggal tersebut.');
            }

            $bank = Db::one('SELECT tipe FROM ms_bank_accounts WHERE id = ?', [(int) $data['bank_account_id']]);
            $metode = $data['tipe'] === 'transfer'
                ? 'transfer'
                : ((string) ($bank['tipe'] ?? 'bank') === 'petty_cash' ? 'kas' : 'bank');

            $payload = [
                'tanggal'                 => $data['tanggal'],
                'periode_id'              => (int) $periode['id'],
                'tipe'                    => $data['tipe'],
                'bank_account_id'         => $data['bank_account_id'],
                'bank_account_tujuan_id'  => $data['tipe'] === 'transfer' ? $data['bank_account_tujuan_id'] : null,
                'coa_lawan_id'            => $data['tipe'] === 'transfer'
                    ? AccountMap::bankCoa((int) $data['bank_account_tujuan_id'])
                    : $data['coa_lawan_id'],
                'partner_id'              => $data['partner_id'],
                'project_id'              => $data['project_id'],
                'bidang_id'               => $data['bidang_id'],
                'divisi_id'               => $data['divisi_id'],
                'cost_center_id'          => $data['cost_center_id'],
                'jumlah'                  => number_format((float) $data['jumlah'], 2, '.', ''),
                'metode'                  => $metode,
                'referensi'               => $data['referensi'] !== '' ? $data['referensi'] : null,
                'deskripsi'               => $data['deskripsi'],
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next(self::kodeDokumen((string) $data['tipe']), (string) $data['tanggal']);
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                return Db::insert('cash_transactions', $payload);
            }

            Db::update('cash_transactions', $payload, 'id = :id', [':id' => $id]);
            return $id;
        });
    }

    public static function kodeDokumen(string $tipe): string
    {
        return match ($tipe) {
            'masuk'    => 'KM',
            'keluar'   => 'KK',
            'transfer' => 'TRF',
            default    => 'KK',
        };
    }

    public static function ajukan(int $id): void
    {
        $row = Db::one('SELECT status, periode_id FROM cash_transactions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Transaksi tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Hanya transaksi draft/ditolak yang dapat diajukan.');
        }
        Db::exec('UPDATE cash_transactions SET status = ? WHERE id = ?', ['submitted', $id]);
    }

    public static function setujui(int $id): void
    {
        $row = Db::one('SELECT status FROM cash_transactions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Transaksi tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya transaksi yang diajukan dapat disetujui.');
        }
        Db::exec('UPDATE cash_transactions SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?', [
            'approved', auth_id() ?: null, now(), $id,
        ]);
    }

    public static function tolak(int $id, string $alasan = ''): void
    {
        $row = Db::one('SELECT nomor, status FROM cash_transactions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Transaksi tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya transaksi yang diajukan dapat ditolak.');
        }
        Db::exec('UPDATE cash_transactions SET status = ? WHERE id = ?', ['rejected', $id]);

        Audit::log('reject', 'cash', [
            'ref_table'   => 'cash_transactions',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tolak transaksi kas: ' . ($alasan !== '' ? $alasan : 'tanpa alasan'),
        ]);
    }

    /**
     * Posting: buat jurnal otomatis + sesuaikan saldo rekening.
     */
    public static function posting(int $id): int
    {
        return Db::transaction(static function () use ($id): int {
            $row = Db::one('SELECT * FROM cash_transactions WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Transaksi tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new \RuntimeException('Transaksi harus disetujui sebelum diposting.');
            }
            if (!PeriodService::isOpen((int) $row['periode_id'])) {
                throw new \RuntimeException('Periode transaksi sudah ditutup.');
            }

            $jumlah = (float) $row['jumlah'];
            $bankCoa = AccountMap::bankCoa((int) $row['bank_account_id']);
            $dimensi = [
                'bidang_id'      => $row['bidang_id'] ?: null,
                'divisi_id'      => $row['divisi_id'] ?: null,
                'project_id'     => $row['project_id'] ?: null,
                'cost_center_id' => $row['cost_center_id'] ?: null,
                'partner_id'     => $row['partner_id'] ?: null,
            ];

            $tipe = (string) $row['tipe'];
            if ($tipe === 'masuk') {
                $lines = [
                    array_merge(['coa_id' => $bankCoa, 'deskripsi' => (string) $row['deskripsi'], 'debit' => $jumlah, 'kredit' => 0.0], $dimensi),
                    array_merge(['coa_id' => (int) $row['coa_lawan_id'], 'deskripsi' => (string) $row['deskripsi'], 'debit' => 0.0, 'kredit' => $jumlah], $dimensi),
                ];
            } elseif ($tipe === 'keluar') {
                $lines = [
                    array_merge(['coa_id' => (int) $row['coa_lawan_id'], 'deskripsi' => (string) $row['deskripsi'], 'debit' => $jumlah, 'kredit' => 0.0], $dimensi),
                    array_merge(['coa_id' => $bankCoa, 'deskripsi' => (string) $row['deskripsi'], 'debit' => 0.0, 'kredit' => $jumlah], $dimensi),
                ];
            } else {
                $tujuanCoa = AccountMap::bankCoa((int) $row['bank_account_tujuan_id']);
                $lines = [
                    ['coa_id' => $tujuanCoa, 'deskripsi' => 'Transfer masuk — ' . $row['nomor'], 'debit' => $jumlah, 'kredit' => 0.0],
                    ['coa_id' => $bankCoa, 'deskripsi' => 'Transfer keluar — ' . $row['nomor'], 'debit' => 0.0, 'kredit' => $jumlah],
                ];
            }

            $journalId = JournalService::catat([
                'tanggal'      => (string) $row['tanggal'],
                'tipe'         => $tipe === 'transfer' ? 'transfer' : ($tipe === 'masuk' ? 'penerimaan' : 'pengeluaran'),
                'sumber'       => 'kas_bank',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => (string) $row['deskripsi'],
                'kode_dokumen' => self::kodeDokumen($tipe),
            ], $lines);

            Db::exec('UPDATE cash_transactions SET status = ?, journal_id = ?, posted_at = ? WHERE id = ?', [
                'posted', $journalId, now(), $id,
            ]);

            // penyesuaian saldo rekening
            $delta = $tipe === 'masuk' ? $jumlah : -$jumlah;
            Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan + ? WHERE id = ?', [
                number_format($delta, 2, '.', ''), (int) $row['bank_account_id'],
            ]);
            if ($tipe === 'transfer') {
                Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan + ? WHERE id = ?', [
                    number_format($jumlah, 2, '.', ''), (int) $row['bank_account_tujuan_id'],
                ]);
            }

            Audit::log('post', 'cash', [
                'ref_table'   => 'cash_transactions',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Posting transaksi kas/bank ' . $row['nomor'],
                'new'         => ['journal_id' => $journalId, 'jumlah' => $row['jumlah']],
            ]);

            return $journalId;
        });
    }

    public static function hapus(int $id): void
    {
        $row = Db::one('SELECT nomor, status FROM cash_transactions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Transaksi tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Transaksi yang sudah diajukan tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM cash_transactions WHERE id = ?', [$id]);
    }

    /** Ringkasan untuk header halaman. */
    public static function ringkasan(int $tahun): array
    {
        $rows = Db::all(
            'SELECT tipe, status, COUNT(*) AS jumlah, COALESCE(SUM(jumlah),0) AS nilai
             FROM cash_transactions WHERE periode_id IN (SELECT id FROM fiscal_periods WHERE tahun = ?)
             GROUP BY tipe, status',
            [$tahun]
        );

        $hasil = ['masuk' => 0.0, 'keluar' => 0.0, 'transfer' => 0.0, 'menunggu' => 0, 'posted' => 0];
        foreach ($rows as $row) {
            if ((string) $row['status'] === 'posted') {
                $hasil[(string) $row['tipe']] = ($hasil[(string) $row['tipe']] ?? 0.0) + (float) $row['nilai'];
                $hasil['posted'] += (int) $row['jumlah'];
            } elseif (in_array((string) $row['status'], ['submitted', 'approved'], true)) {
                $hasil['menunggu'] += (int) $row['jumlah'];
            }
        }
        return $hasil;
    }
}
