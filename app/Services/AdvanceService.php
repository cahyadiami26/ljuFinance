<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Uang muka kegiatan (cash advance).
 *
 * Alur: draft → diajukan → disetujui → cair (jurnal pencairan) →
 * pertanggungjawaban (rincian biaya) → selesai (jurnal penyelesaian).
 *
 * Jurnal pencairan  : D Uang Muka, K Bank
 * Jurnal penyelesaian: D Beban (rincian), K Uang Muka (sebesar pencairan),
 *                      selisih sisa → D Bank (kembali) atau K Bank (kurang bayar).
 */
final class AdvanceService
{
    public const STATUS = [
        'draft'               => 'Draft',
        'submitted'           => 'Diajukan',
        'approved'            => 'Disetujui',
        'rejected'            => 'Ditolak',
        'cair'                => 'Dicairkan',
        'pertanggungjawaban'  => 'Pertanggungjawaban',
        'selesai'             => 'Selesai',
        'void'                => 'Dibatalkan',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT a.*, u.name AS pemohon_nama, u.position AS pemohon_jabatan,
                    b.nama AS bank_nama, b.kode AS bank_kode,
                    bd.nama AS bidang_nama, dv.nama AS divisi_nama, pr.nama AS project_nama, cc.nama AS cost_center_nama,
                    p.nama AS periode_nama, ap.name AS approved_by_name,
                    j1.nomor AS journal_nomor, j2.nomor AS journal_settlement_nomor
             FROM cash_advances a
             LEFT JOIN app_users u ON u.id = a.pemohon_user_id
             LEFT JOIN app_users ap ON ap.id = a.approved_by
             LEFT JOIN ms_bank_accounts b ON b.id = a.bank_account_id
             LEFT JOIN ms_bidang bd ON bd.id = a.bidang_id
             LEFT JOIN ms_divisi dv ON dv.id = a.divisi_id
             LEFT JOIN ms_projects pr ON pr.id = a.project_id
             LEFT JOIN ms_cost_centers cc ON cc.id = a.cost_center_id
             LEFT JOIN fiscal_periods p ON p.id = a.periode_id
             LEFT JOIN journal_entries j1 ON j1.id = a.journal_id
             LEFT JOIN journal_entries j2 ON j2.id = a.journal_settlement_id
             WHERE a.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $id): array
    {
        return Db::all(
            'SELECT i.*, c.kode AS coa_kode, c.nama AS coa_nama, t.nama AS pajak_nama, t.tarif AS pajak_tarif
             FROM cash_advance_items i
             JOIN coa_accounts c ON c.id = i.coa_id
             LEFT JOIN tax_types t ON t.id = i.pajak_type_id
             WHERE i.advance_id = ?
             ORDER BY i.tanggal, i.id',
            [$id]
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasi(array $input): array
    {
        return [
            'tanggal'          => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'pemohon_user_id'  => (int) ($input['pemohon_user_id'] ?? 0) ?: auth_id(),
            'bagian'           => trim((string) ($input['bagian'] ?? '')),
            'bidang_id'        => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'        => (int) ($input['divisi_id'] ?? 0) ?: null,
            'cost_center_id'   => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'project_id'       => (int) ($input['project_id'] ?? 0) ?: null,
            'keperluan'        => trim((string) ($input['keperluan'] ?? '')),
            'jumlah_diajukan'  => JournalService::uang($input['jumlah_diajukan'] ?? 0),
            'bank_account_id'  => (int) ($input['bank_account_id'] ?? 0) ?: null,
            'catatan'          => trim((string) ($input['catatan'] ?? '')),
        ];
    }

    /** @return array<string,string> */
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

        if ($data['keperluan'] === '') {
            $errors['keperluan'] = 'Keperluan wajib diisi.';
        } elseif (mb_strlen((string) $data['keperluan']) > 255) {
            $errors['keperluan'] = 'Keperluan maksimal 255 karakter.';
        }

        if ($data['jumlah_diajukan'] <= 0) {
            $errors['jumlah_diajukan'] = 'Jumlah pengajuan harus lebih besar dari nol.';
        }

        if (empty($data['pemohon_user_id'])) {
            $errors['pemohon_user_id'] = 'Pemohon wajib dipilih.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi tidak tersedia.');
            }

            $payload = [
                'tanggal'          => $data['tanggal'],
                'periode_id'       => (int) $periode['id'],
                'pemohon_user_id'  => (int) $data['pemohon_user_id'],
                'bagian'           => $data['bagian'] !== '' ? mb_substr((string) $data['bagian'], 0, 100) : null,
                'bidang_id'        => $data['bidang_id'],
                'divisi_id'        => $data['divisi_id'],
                'cost_center_id'   => $data['cost_center_id'],
                'project_id'       => $data['project_id'],
                'keperluan'        => $data['keperluan'],
                'jumlah_diajukan'  => number_format((float) $data['jumlah_diajukan'], 2, '.', ''),
                'bank_account_id'  => $data['bank_account_id'],
                'catatan'          => $data['catatan'] !== '' ? mb_substr((string) $data['catatan'], 0, 255) : null,
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next('CA', (string) $data['tanggal']);
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                return Db::insert('cash_advances', $payload);
            }

            Db::update('cash_advances', $payload, 'id = :id', [':id' => $id]);
            return $id;
        });
    }

    public static function ajukan(int $id): void
    {
        $row = Db::one('SELECT status FROM cash_advances WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Uang muka tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Hanya draft yang dapat diajukan.');
        }
        Db::exec('UPDATE cash_advances SET status = ? WHERE id = ?', ['submitted', $id]);
    }

    public static function setujui(int $id, ?float $jumlahDisetujui = null): void
    {
        $row = Db::one('SELECT * FROM cash_advances WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Uang muka tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya pengajuan yang dapat disetujui.');
        }

        $nilai = $jumlahDisetujui ?? (float) $row['jumlah_diajukan'];
        if ($nilai <= 0) {
            throw new \RuntimeException('Jumlah disetujui harus lebih besar dari nol.');
        }
        if ($nilai > (float) $row['jumlah_diajukan'] * 1.0001) {
            throw new \RuntimeException('Jumlah disetujui tidak boleh melebihi pengajuan.');
        }

        Db::exec('UPDATE cash_advances SET status = ?, jumlah_disetujui = ?, approved_by = ?, approved_at = ? WHERE id = ?', [
            'approved', number_format($nilai, 2, '.', ''), auth_id() ?: null, now(), $id,
        ]);
    }

    public static function tolak(int $id, string $alasan = ''): void
    {
        $row = Db::one('SELECT nomor, status FROM cash_advances WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Uang muka tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya pengajuan yang dapat ditolak.');
        }
        Db::exec('UPDATE cash_advances SET status = ? WHERE id = ?', ['rejected', $id]);

        Audit::log('reject', 'advance', [
            'ref_table'   => 'cash_advances',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tolak uang muka: ' . ($alasan !== '' ? $alasan : 'tanpa alasan'),
        ]);
    }

    /**
     * Pencairan uang muka: D Uang Muka / K Bank.
     */
    public static function cairkan(int $id, int $bankAccountId, string $tanggal, ?float $jumlah = null): int
    {
        return Db::transaction(static function () use ($id, $bankAccountId, $tanggal, $jumlah): int {
            $row = Db::one('SELECT * FROM cash_advances WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Uang muka tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new \RuntimeException('Uang muka harus disetujui sebelum dicairkan.');
            }

            $nilai = $jumlah ?? (float) $row['jumlah_disetujui'];
            if ($nilai <= 0) {
                throw new \RuntimeException('Jumlah pencairan tidak valid.');
            }

            $periode = PeriodService::forDate($tanggal);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode tanggal pencairan tidak terbuka.');
            }

            $bankCoa = AccountMap::bankCoa($bankAccountId);
            $uangMuka = AccountMap::uangMuka();

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'pengeluaran',
                'sumber'       => 'uang_muka',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Pencairan uang muka — ' . $row['keperluan'],
                'kode_dokumen' => 'KK',
            ], [
                [
                    'coa_id' => $uangMuka, 'deskripsi' => 'Uang muka ' . $row['nomor'],
                    'debit' => $nilai, 'kredit' => 0.0,
                    'bidang_id' => $row['bidang_id'] ?: null, 'divisi_id' => $row['divisi_id'] ?: null,
                    'project_id' => $row['project_id'] ?: null, 'cost_center_id' => $row['cost_center_id'] ?: null,
                ],
                [
                    'coa_id' => $bankCoa, 'deskripsi' => 'Pencairan uang muka ' . $row['nomor'],
                    'debit' => 0.0, 'kredit' => $nilai,
                ],
            ]);

            Db::exec(
                'UPDATE cash_advances SET status = ?, jumlah_dicairkan = ?, jumlah_disetujui = IF(jumlah_disetujui = 0, ?, jumlah_disetujui),
                    tanggal_cair = ?, bank_account_id = ?, journal_id = ?, sisa = ? WHERE id = ?',
                ['cair', number_format($nilai, 2, '.', ''), number_format($nilai, 2, '.', ''), $tanggal, $bankAccountId, $journalId, number_format($nilai, 2, '.', ''), $id]
            );

            Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan - ? WHERE id = ?', [
                number_format($nilai, 2, '.', ''), $bankAccountId,
            ]);

            Audit::log('post', 'advance', [
                'ref_table'   => 'cash_advances',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Pencairan uang muka ' . $row['nomor'],
                'new'         => ['jumlah' => $nilai, 'journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    /**
     * Simpan rincian pertanggungjawaban (belum membuat jurnal).
     *
     * @param array<int,array<string,mixed>> $items
     */
    public static function simpanItems(int $id, array $items): void
    {
        Db::transaction(static function () use ($id, $items): void {
            $row = Db::one('SELECT * FROM cash_advances WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Uang muka tidak ditemukan.');
            }
            if (!in_array((string) $row['status'], ['cair', 'pertanggungjawaban'], true)) {
                throw new \RuntimeException('Pertanggungjawaban hanya dapat diisi setelah dana dicairkan.');
            }

            Db::exec('DELETE FROM cash_advance_items WHERE advance_id = ?', [$id]);

            $total = 0.0;
            foreach ($items as $item) {
                $jumlah = (float) ($item['jumlah'] ?? 0);
                $coaId = (int) ($item['coa_id'] ?? 0);
                if ($coaId <= 0 || $jumlah <= 0) {
                    continue;
                }
                $akun = Db::one('SELECT allow_posting, is_active, kode FROM coa_accounts WHERE id = ?', [$coaId]);
                if ($akun === null || (int) $akun['allow_posting'] !== 1 || (int) $akun['is_active'] !== 1) {
                    throw new \RuntimeException('Akun ' . ($akun['kode'] ?? $coaId) . ' tidak boleh menerima posting.');
                }
                Db::insert('cash_advance_items', [
                    'advance_id'    => $id,
                    'tanggal'       => $item['tanggal'] ?? null,
                    'keterangan'    => mb_substr((string) ($item['keterangan'] ?? ''), 0, 255),
                    'coa_id'        => (int) $item['coa_id'],
                    'jumlah'        => number_format($jumlah, 2, '.', ''),
                    'pajak_type_id' => (int) ($item['pajak_type_id'] ?? 0) ?: null,
                    'nilai_pajak'   => number_format((float) ($item['nilai_pajak'] ?? 0), 2, '.', ''),
                    'no_bukti'      => isset($item['no_bukti']) && $item['no_bukti'] !== '' ? mb_substr((string) $item['no_bukti'], 0, 80) : null,
                ]);
                $total += $jumlah;
            }

            $status = $total > 0 ? 'pertanggungjawaban' : 'cair';
            Db::exec(
                'UPDATE cash_advances SET status = ?, jumlah_dipertanggungjawabkan = ?, sisa = jumlah_dicairkan - ? WHERE id = ?',
                [$status, number_format($total, 2, '.', ''), number_format($total, 2, '.', ''), $id]
            );
        });
    }

    /**
     * Penyelesaian: jurnal beban + pengembalian/pelunasan selisih.
     */
    public static function selesaikan(int $id, int $bankAccountId, string $tanggal): int
    {
        return Db::transaction(static function () use ($id, $bankAccountId, $tanggal): int {
            $row = Db::one('SELECT * FROM cash_advances WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Uang muka tidak ditemukan.');
            }
            if (!in_array((string) $row['status'], ['pertanggungjawaban', 'cair'], true)) {
                throw new \RuntimeException('Uang muka belum dalam tahap pertanggungjawaban.');
            }

            $items = self::items($id);
            if ($items === []) {
                throw new \RuntimeException('Isi rincian pertanggungjawaban terlebih dahulu.');
            }

            $dipakai = 0.0;
            foreach ($items as $item) {
                $dipakai += (float) $item['jumlah'];
            }

            $dicairkan = (float) $row['jumlah_dicairkan'];
            $sisa = round($dicairkan - $dipakai, 2);

            $periode = PeriodService::forDate($tanggal);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode tanggal penyelesaian tidak terbuka.');
            }

            $lines = [];
            foreach ($items as $item) {
                $lines[] = [
                    'coa_id'     => (int) $item['coa_id'],
                    'deskripsi'  => (string) $item['keterangan'],
                    'debit'      => (float) $item['jumlah'],
                    'kredit'     => 0.0,
                    'bidang_id'  => $row['bidang_id'] ?: null,
                    'divisi_id'  => $row['divisi_id'] ?: null,
                    'project_id' => $row['project_id'] ?: null,
                    'cost_center_id' => $row['cost_center_id'] ?: null,
                ];
            }

            $lines[] = [
                'coa_id'    => AccountMap::uangMuka(),
                'deskripsi' => 'Penyelesaian uang muka ' . $row['nomor'],
                'debit'     => 0.0,
                'kredit'    => $dicairkan,
                'bidang_id' => $row['bidang_id'] ?: null,
                'divisi_id' => $row['divisi_id'] ?: null,
                'project_id' => $row['project_id'] ?: null,
            ];

            $bankCoa = AccountMap::bankCoa($bankAccountId);
            if ($sisa > 0.005) {
                $lines[] = [
                    'coa_id'    => $bankCoa,
                    'deskripsi' => 'Pengembalian sisa uang muka ' . $row['nomor'],
                    'debit'     => $sisa,
                    'kredit'    => 0.0,
                ];
            } elseif ($sisa < -0.005) {
                $lines[] = [
                    'coa_id'    => $bankCoa,
                    'deskripsi' => 'Kekurangan dana uang muka ' . $row['nomor'],
                    'debit'     => 0.0,
                    'kredit'    => abs($sisa),
                ];
            }

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'pengeluaran',
                'sumber'       => 'uang_muka',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Pertanggungjawaban uang muka — ' . $row['keperluan'],
                'kode_dokumen' => 'EXP',
            ], $lines);

            Db::exec(
                'UPDATE cash_advances SET status = ?, journal_settlement_id = ?, jumlah_dipertanggungjawabkan = ?, sisa = ? WHERE id = ?',
                ['selesai', $journalId, number_format($dipakai, 2, '.', ''), number_format($sisa, 2, '.', ''), $id]
            );

            if ($sisa > 0.005) {
                Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan + ? WHERE id = ?', [
                    number_format($sisa, 2, '.', ''), $bankAccountId,
                ]);
            } elseif ($sisa < -0.005) {
                Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan - ? WHERE id = ?', [
                    number_format(abs($sisa), 2, '.', ''), $bankAccountId,
                ]);
            }

            Audit::log('update', 'advance', [
                'ref_table'   => 'cash_advances',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Penyelesaian uang muka ' . $row['nomor'],
                'new'         => ['dipakai' => $dipakai, 'sisa' => $sisa, 'journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    public static function hapus(int $id): void
    {
        $row = Db::one('SELECT status FROM cash_advances WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Uang muka tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Pengajuan yang sudah diproses tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM cash_advances WHERE id = ?', [$id]);
    }
}
