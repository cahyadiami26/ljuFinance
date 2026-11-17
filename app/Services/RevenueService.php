<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Pengakuan pendapatan kontrak (revenue recognition).
 *
 * Jurnal: D Piutang Belum Ditagih, K Pendapatan Usaha.
 * Saat invoice pelanggan atas kontrak yang sama diposting, bagian yang sudah
 * diakui dikreditkan ke Piutang Belum Ditagih (lihat ArService::posting()).
 */
final class RevenueService
{
    public const STATUS = [
        'draft'     => 'Draft',
        'submitted' => 'Diajukan',
        'approved'  => 'Disetujui',
        'posted'    => 'Posted',
        'void'      => 'Dibatalkan',
    ];

    public const METODE = [
        'persentase_penyelesaian' => 'Persentase Penyelesaian',
        'termin'                  => 'Termin / Progres Tagihan',
        'kontrak_selesai'         => 'Kontrak Selesai',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT r.*, pr.nama AS project_nama, k.nomor AS contract_nomor, k.judul AS contract_judul,
                    k.nilai_kontrak, p.nama AS partner_nama, p.id AS partner_id,
                    pe.nama AS periode_nama, u.name AS created_by_name,
                    j.nomor AS journal_nomor, j.status AS journal_status
             FROM revenue_recognitions r
             LEFT JOIN ms_projects pr ON pr.id = r.project_id
             LEFT JOIN ms_contracts k ON k.id = r.contract_id
             LEFT JOIN ms_partners p ON p.id = k.partner_id
             LEFT JOIN fiscal_periods pe ON pe.id = r.periode_id
             LEFT JOIN app_users u ON u.id = r.created_by
             LEFT JOIN journal_entries j ON j.id = r.journal_id
             WHERE r.id = ?',
            [$id]
        );
    }

    /** Total nilai yang sudah diakui untuk sebuah kontrak (posted). */
    public static function terakuiKontrak(int $contractId, ?int $kecualiId = null): float
    {
        return (float) Db::val(
            "SELECT COALESCE(SUM(nilai),0) FROM revenue_recognitions
             WHERE contract_id = ? AND status = 'posted' AND id <> ?",
            [$contractId, $kecualiId ?? 0]
        );
    }

    /**
     * Nilai pengakuan yang disarankan berdasarkan metode.
     */
    public static function hitungNilai(string $metode, float $persentase, ?float $nilaiKontrak, int $contractId, ?int $kecualiId = null): float
    {
        $nilaiKontrak = (float) ($nilaiKontrak ?? 0);

        if ($metode === 'persentase_penyelesaian') {
            $persen = max(0.0, min(100.0, $persentase));
            if ($nilaiKontrak <= 0) {
                return 0.0;
            }
            $sudah = self::terakuiKontrak($contractId, $kecualiId);
            $target = round($nilaiKontrak * $persen / 100, 2);
            return max(0.0, round($target - $sudah, 2));
        }

        if ($metode === 'kontrak_selesai' && $nilaiKontrak > 0 && $contractId > 0) {
            $sudah = self::terakuiKontrak($contractId, $kecualiId);
            return max(0.0, round($nilaiKontrak - $sudah, 2));
        }

        return 0.0;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasi(array $input): array
    {
        $tanggal = trim((string) ($input['tanggal'] ?? '')) ?: today();
        $metode = (string) ($input['metode'] ?? 'persentase_penyelesaian');
        if (!array_key_exists($metode, self::METODE)) {
            $metode = 'persentase_penyelesaian';
        }

        $contractId = (int) ($input['contract_id'] ?? 0);
        $persentase = JournalService::uang($input['persentase'] ?? 0);
        $persentase = max(0.0, min(100.0, $persentase));

        $nilaiKontrak = null;
        if ($contractId > 0) {
            $nilaiKontrak = Db::val('SELECT nilai_kontrak FROM ms_contracts WHERE id = ?', [$contractId]);
        }

        $nilai = JournalService::uang($input['nilai'] ?? 0);
        if ($metode === 'persentase_penyelesaian') {
            $hitung = self::hitungNilai($metode, $persentase, $nilaiKontrak !== null ? (float) $nilaiKontrak : null, $contractId);
            if ($hitung > 0) {
                $nilai = $hitung;
            }
        } elseif ($nilai <= 0) {
            $saran = self::hitungNilai($metode, $persentase, $nilaiKontrak !== null ? (float) $nilaiKontrak : null, $contractId);
            if ($saran > 0) {
                $nilai = $saran;
            }
        }

        return [
            'tanggal'    => $tanggal,
            'project_id' => (int) ($input['project_id'] ?? 0) ?: null,
            'contract_id' => $contractId ?: null,
            'persentase' => $persentase,
            'nilai'      => $nilai,
            'metode'     => $metode,
            'keterangan' => trim((string) ($input['keterangan'] ?? '')),
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        if (strtotime((string) $data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal pengakuan tidak valid.';
        } else {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                $errors['tanggal'] = 'Periode akuntansi untuk tanggal ini belum tersedia.';
            } elseif ((string) $periode['status'] !== 'open') {
                $errors['tanggal'] = 'Periode ' . $periode['nama'] . ' sudah ditutup/dikunci.';
            }
        }

        if ($data['project_id'] === null && $data['contract_id'] === null) {
            $errors['contract_id'] = 'Pilih proyek atau kontrak yang diakui pendapatannya.';
        }

        if ($data['nilai'] <= 0) {
            $errors['nilai'] = 'Nilai pengakuan pendapatan harus lebih besar dari nol.';
        }

        if ($data['metode'] === 'persentase_penyelesaian' && ($data['persentase'] <= 0 || $data['persentase'] > 100)) {
            $errors['persentase'] = 'Persentase penyelesaian harus di antara 0 dan 100.';
        }

        if ($data['contract_id'] !== null && !isset($errors['contract_id'])) {
            $kontrak = Db::one('SELECT nilai_kontrak, status FROM ms_contracts WHERE id = ?', [(int) $data['contract_id']]);
            if ($kontrak === null) {
                $errors['contract_id'] = 'Kontrak tidak ditemukan.';
            } elseif ((string) $kontrak['status'] === 'batal') {
                $errors['contract_id'] = 'Kontrak sudah dibatalkan.';
            } elseif ((float) $kontrak['nilai_kontrak'] > 0 && !isset($errors['nilai'])) {
                $nilaiKontrak = (float) $kontrak['nilai_kontrak'];
                $terakui = self::terakuiKontrak((int) $data['contract_id']);

                if ($data['nilai'] > $nilaiKontrak + 0.005) {
                    $errors['nilai'] = 'Nilai pengakuan melebihi nilai kontrak (' . rupiah($nilaiKontrak) . ').';
                } elseif ($terakui + $data['nilai'] > $nilaiKontrak + 0.005) {
                    $errors['nilai'] = 'Total pengakuan akan melebihi nilai kontrak (' . rupiah($nilaiKontrak) . '). Sudah diakui: ' . rupiah($terakui) . '.';
                }
            }
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi tidak ditemukan.');
            }

            $payload = [
                'project_id'  => $data['project_id'],
                'contract_id' => $data['contract_id'],
                'periode_id'  => (int) $periode['id'],
                'tanggal'     => $data['tanggal'],
                'persentase'  => number_format((float) $data['persentase'], 2, '.', ''),
                'nilai'       => number_format((float) $data['nilai'], 2, '.', ''),
                'metode'      => $data['metode'],
                'keterangan'  => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next('REV', (string) $data['tanggal']);
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                return Db::insert('revenue_recognitions', $payload);
            }

            Db::update('revenue_recognitions', $payload, 'id = :id', [':id' => $id]);
            return $id;
        });
    }

    public static function ajukan(int $id): void
    {
        $row = Db::one('SELECT * FROM revenue_recognitions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pengakuan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            throw new \RuntimeException('Hanya draft yang dapat diajukan.');
        }
        Db::exec('UPDATE revenue_recognitions SET status = ? WHERE id = ?', ['submitted', $id]);

        ApprovalService::daftarkan('revenue', $id, [
            'nomor'      => (string) $row['nomor'],
            'judul'      => 'Pengakuan pendapatan ' . $row['nomor'],
            'amount'     => (float) $row['nilai'],
            'project_id' => $row['project_id'] ?? null,
        ]);
    }

    public static function setujui(int $id): void
    {
        $row = Db::one('SELECT status FROM revenue_recognitions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pengakuan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya dokumen yang diajukan dapat disetujui.');
        }
        Db::exec('UPDATE revenue_recognitions SET status = ? WHERE id = ?', ['approved', $id]);
        ApprovalService::selesaikanOlehModul('revenue', $id, 'approved');
    }

    public static function tolak(int $id, string $alasan = ''): void
    {
        $row = Db::one('SELECT nomor, status FROM revenue_recognitions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pengakuan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya dokumen yang diajukan dapat ditolak.');
        }
        Db::exec('UPDATE revenue_recognitions SET status = ? WHERE id = ?', ['draft', $id]);
        ApprovalService::selesaikanOlehModul('revenue', $id, 'rejected');

        Audit::log('reject', 'revenue', [
            'ref_table'   => 'revenue_recognitions',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tolak pengakuan pendapatan: ' . ($alasan !== '' ? $alasan : 'tanpa alasan'),
        ]);
    }

    public static function posting(int $id): int
    {
        return Db::transaction(static function () use ($id): int {
            $row = Db::one('SELECT * FROM revenue_recognitions WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Dokumen pengakuan tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new \RuntimeException('Dokumen harus disetujui sebelum diposting.');
            }

            $periode = PeriodService::forDate((string) $row['tanggal']);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode pengakuan sudah ditutup/dikunci.');
            }

            $nilai = (float) $row['nilai'];
            if ($nilai <= 0) {
                throw new \RuntimeException('Nilai pengakuan tidak valid.');
            }

            $dimensi = [
                'project_id' => $row['project_id'] ?: null,
            ];

            $lines = [
                array_merge([
                    'coa_id'    => AccountMap::piutangBelumDitagih(),
                    'deskripsi' => 'Piutang belum ditagih ' . $row['nomor'],
                    'debit'     => $nilai,
                    'kredit'    => 0.0,
                ], $dimensi),
                array_merge([
                    'coa_id'    => AccountMap::pendapatanUsaha(),
                    'deskripsi' => 'Pengakuan pendapatan ' . $row['nomor'],
                    'debit'     => 0.0,
                    'kredit'    => $nilai,
                ], $dimensi),
            ];

            $journalId = JournalService::catat([
                'tanggal'      => (string) $row['tanggal'],
                'tipe'         => 'penerimaan',
                'sumber'       => 'ar',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Pengakuan pendapatan ' . $row['nomor'],
                'kode_dokumen' => 'REV',
            ], $lines);

            Db::exec('UPDATE revenue_recognitions SET status = ?, journal_id = ? WHERE id = ?', [
                'posted', $journalId, $id,
            ]);

            Audit::log('post', 'revenue', [
                'ref_table'   => 'revenue_recognitions',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Posting pengakuan pendapatan ' . $row['nomor'],
                'new'         => ['journal_id' => $journalId, 'nilai' => $nilai],
            ]);

            return $journalId;
        });
    }

    public static function hapus(int $id): void
    {
        $row = Db::one('SELECT status FROM revenue_recognitions WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pengakuan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            throw new \RuntimeException('Dokumen yang sudah diajukan tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM revenue_recognitions WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed> */
    public static function ringkasan(int $tahun): array
    {
        $row = Db::one(
            "SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(nilai),0) AS total,
                    COALESCE(SUM(CASE WHEN status = 'posted' THEN nilai ELSE 0 END),0) AS posted,
                    COALESCE(SUM(CASE WHEN status IN ('draft','submitted','approved') THEN nilai ELSE 0 END),0) AS proses
             FROM revenue_recognitions
             WHERE YEAR(tanggal) = ?",
            [$tahun]
        );

        return [
            'jumlah' => (int) ($row['jumlah'] ?? 0),
            'total'  => (float) ($row['total'] ?? 0),
            'posted' => (float) ($row['posted'] ?? 0),
            'proses' => (float) ($row['proses'] ?? 0),
        ];
    }
}
