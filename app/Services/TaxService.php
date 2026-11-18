<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Perpajakan: dokumen pajak (faktur keluaran/masukan, bukti potong, SSP),
 * penyetoran pajak, rekap bulanan (SPT), dan persiapan e-Faktur.
 */
final class TaxService
{
    public const TIPE = [
        'faktur_keluaran' => 'Faktur Keluaran',
        'faktur_masukan'  => 'Faktur Masukan',
        'bukti_potong'    => 'Bukti Potong',
        'ssp'             => 'SSP / Penyetoran',
        'lain'            => 'Dokumen Lain',
    ];

    public const STATUS = [
        'draft'    => 'Draft',
        'posted'   => 'Posted',
        'reported' => 'Dilaporkan',
        'void'     => 'Void',
    ];

    public const JENIS = [
        'ppn'      => 'PPN',
        'pph21'    => 'PPh 21',
        'pph22'    => 'PPh 22',
        'pph23'    => 'PPh 23',
        'pph4_2'   => 'PPh 4(2)',
        'pph_final' => 'PPh Final',
        'lain'     => 'Lainnya',
    ];

    public const REPORT_JENIS = [
        'ppn'      => 'SPT PPN',
        'pph21'    => 'SPT PPh 21',
        'pph23'    => 'SPT PPh 23',
        'pph4_2'   => 'SPT PPh 4(2)',
        'pph_badan' => 'PPh Badan',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT t.*, tt.kode AS tax_kode, tt.nama AS tax_nama, tt.jenis, tt.coa_keluaran_id, tt.coa_masukan_id,
                    p.nama AS partner_nama, pe.nama AS periode_nama, j.nomor AS journal_nomor, u.name AS created_by_name
             FROM tax_documents t
             JOIN tax_types tt ON tt.id = t.tax_type_id
             LEFT JOIN ms_partners p ON p.id = t.partner_id
             LEFT JOIN fiscal_periods pe ON pe.id = t.periode_id
             LEFT JOIN journal_entries j ON j.id = t.journal_id
             LEFT JOIN app_users u ON u.id = t.created_by
             WHERE t.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function types(): array
    {
        return Db::all('SELECT * FROM tax_types WHERE is_active = 1 ORDER BY kode');
    }

    /**
     * Ringkasan pajak satu periode (default periode aktif).
     *
     * @return array<string,mixed>
     */
    public static function ringkasan(?int $periodeId = null): array
    {
        $where = "status <> 'void'";
        $params = [];
        if ($periodeId !== null && $periodeId > 0) {
            $where .= ' AND periode_id = ?';
            $params[] = $periodeId;
        }

        $row = Db::one(
            "SELECT COALESCE(SUM(CASE WHEN tipe = 'faktur_keluaran' THEN dpp ELSE 0 END),0) AS keluaran_dpp,
                    COALESCE(SUM(CASE WHEN tipe = 'faktur_keluaran' THEN nilai_pajak ELSE 0 END),0) AS keluaran_pajak,
                    COALESCE(SUM(CASE WHEN tipe = 'faktur_masukan' THEN dpp ELSE 0 END),0) AS masukan_dpp,
                    COALESCE(SUM(CASE WHEN tipe = 'faktur_masukan' THEN nilai_pajak ELSE 0 END),0) AS masukan_pajak,
                    COALESCE(SUM(CASE WHEN tipe = 'bukti_potong' THEN nilai_pajak ELSE 0 END),0) AS potongan,
                    COALESCE(SUM(CASE WHEN tipe = 'ssp' AND status = 'draft' THEN nilai_pajak ELSE 0 END),0) AS ssp_draft,
                    COALESCE(SUM(CASE WHEN tipe = 'ssp' AND status IN ('posted','reported') THEN nilai_pajak ELSE 0 END),0) AS ssp_setor,
                    COUNT(*) AS jumlah
             FROM tax_documents WHERE " . $where,
            $params
        );

        $kurang = max(0.0, (float) $row['keluaran_pajak'] - (float) $row['masukan_pajak']);

        return [
            'jumlah'         => (int) ($row['jumlah'] ?? 0),
            'keluaran_dpp'   => (float) ($row['keluaran_dpp'] ?? 0),
            'keluaran_pajak' => (float) ($row['keluaran_pajak'] ?? 0),
            'masukan_dpp'    => (float) ($row['masukan_dpp'] ?? 0),
            'masukan_pajak'  => (float) ($row['masukan_pajak'] ?? 0),
            'potongan'       => (float) ($row['potongan'] ?? 0),
            'ssp_draft'      => (float) ($row['ssp_draft'] ?? 0),
            'ssp_setor'      => (float) ($row['ssp_setor'] ?? 0),
            'kurang_bayar'   => round($kurang, 2),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalisasi(array $input): array
    {
        $dpp = JournalService::uang($input['dpp'] ?? 0);
        $taxTypeId = (int) ($input['tax_type_id'] ?? 0);
        $tarif = (float) ($input['tarif'] ?? 0);

        if ($tarif <= 0 && $taxTypeId > 0) {
            $tarif = (float) Db::val('SELECT tarif FROM tax_types WHERE id = ?', [$taxTypeId]);
        }

        $nilai = JournalService::uang($input['nilai_pajak'] ?? 0);
        if ($nilai <= 0 && $dpp > 0 && $tarif > 0) {
            $nilai = round($dpp * $tarif / 100, 2);
        }

        $tanggal = trim((string) ($input['tanggal'] ?? '')) ?: today();
        $periode = PeriodService::forDate($tanggal);

        return [
            'nomor'       => trim((string) ($input['nomor'] ?? '')),
            'tipe'        => (string) ($input['tipe'] ?? 'faktur_keluaran'),
            'tax_type_id' => $taxTypeId,
            'partner_id'  => (int) ($input['partner_id'] ?? 0) ?: null,
            'tanggal'     => $tanggal,
            'periode_id'  => (int) ($periode['id'] ?? ($input['periode_id'] ?? 0)),
            'npwp'        => trim((string) ($input['npwp'] ?? '')),
            'dpp'         => $dpp,
            'tarif'       => $tarif,
            'nilai_pajak' => $nilai,
            'ref_module'  => trim((string) ($input['ref_module'] ?? '')),
            'ref_id'      => (int) ($input['ref_id'] ?? 0) ?: null,
            'keterangan'  => trim((string) ($input['keterangan'] ?? '')),
            'bank_account_id' => (int) ($input['bank_account_id'] ?? 0) ?: null,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data, ?int $id = null): array
    {
        $errors = [];

        if (!array_key_exists((string) $data['tipe'], self::TIPE)) {
            $errors['tipe'] = 'Tipe dokumen pajak tidak dikenal.';
        }
        if ((int) $data['tax_type_id'] <= 0) {
            $errors['tax_type_id'] = 'Jenis pajak wajib dipilih.';
        }
        if (strtotime((string) $data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal dokumen tidak valid.';
        }
        if ((int) $data['periode_id'] <= 0) {
            $errors['tanggal'] = 'Tidak ada periode akuntansi untuk tanggal tersebut.';
        }
        if ((float) $data['dpp'] <= 0 && (float) $data['nilai_pajak'] <= 0) {
            $errors['dpp'] = 'DPP atau nilai pajak harus lebih besar dari nol.';
        }
        if ((float) $data['tarif'] < 0 || (float) $data['tarif'] > 100) {
            $errors['tarif'] = 'Tarif pajak harus antara 0 dan 100 persen.';
        }
        if ((float) $data['nilai_pajak'] < 0) {
            $errors['nilai_pajak'] = 'Nilai pajak tidak boleh negatif.';
        }
        if ($data['nomor'] !== '') {
            $duplikat = Db::val(
                'SELECT COUNT(*) FROM tax_documents WHERE nomor = ? AND id <> ?',
                [(string) $data['nomor'], $id ?? 0]
            );
            if ((int) $duplikat > 0) {
                $errors['nomor'] = 'Nomor dokumen pajak sudah dipakai.';
            }
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $nomor = (string) $data['nomor'];
            if ($nomor === '') {
                $nomor = NumberingService::next('TAX', (string) $data['tanggal']);
            }

            $payload = [
                'nomor'       => mb_substr($nomor, 0, 80),
                'tipe'        => (string) $data['tipe'],
                'tax_type_id' => (int) $data['tax_type_id'],
                'partner_id'  => $data['partner_id'],
                'tanggal'     => (string) $data['tanggal'],
                'periode_id'  => (int) $data['periode_id'],
                'npwp'        => $data['npwp'] !== '' ? mb_substr((string) $data['npwp'], 0, 30) : null,
                'dpp'         => number_format((float) $data['dpp'], 2, '.', ''),
                'tarif'       => number_format((float) $data['tarif'], 2, '.', ''),
                'nilai_pajak' => number_format((float) $data['nilai_pajak'], 2, '.', ''),
                'ref_module'  => $data['ref_module'] !== '' ? mb_substr((string) $data['ref_module'], 0, 40) : null,
                'ref_id'      => $data['ref_id'],
                'keterangan'  => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            ];

            if ($id === null) {
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('tax_documents', $payload);
            } else {
                $row = self::find($id);
                if ($row === null) {
                    throw new \RuntimeException('Dokumen pajak tidak ditemukan.');
                }
                if (!in_array((string) $row['status'], ['draft'], true)) {
                    throw new \RuntimeException('Hanya dokumen draft yang dapat diubah.');
                }
                Db::update('tax_documents', $payload, 'id = :id', [':id' => $id]);
            }

            return $id;
        });
    }

    /**
     * Setor pajak (SSP): D utang pajak / K bank, lalu status posted.
     */
    public static function setor(int $id, int $bankAccountId): int
    {
        return Db::transaction(static function () use ($id, $bankAccountId): int {
            $row = self::find($id);
            if ($row === null) {
                throw new \RuntimeException('Dokumen pajak tidak ditemukan.');
            }
            if ((string) $row['tipe'] !== 'ssp') {
                throw new \RuntimeException('Hanya dokumen SSP yang dapat disetorkan.');
            }
            if ((string) $row['status'] !== 'draft') {
                throw new \RuntimeException('Dokumen SSP ini sudah diproses.');
            }
            if ((float) $row['nilai_pajak'] <= 0) {
                throw new \RuntimeException('Nilai pajak belum diisi.');
            }
            if ($bankAccountId <= 0) {
                throw new \RuntimeException('Rekening bank sumber wajib dipilih.');
            }

            $coaPajak = (int) ($row['coa_keluaran_id'] ?? 0);
            if ($coaPajak <= 0) {
                $coaPajak = AccountMap::pajakPotongan((int) $row['tax_type_id']);
            }

            $journalId = JournalService::catat([
                'tanggal'      => (string) $row['tanggal'],
                'tipe'         => 'pengeluaran',
                'sumber'       => 'pajak',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Penyetoran ' . $row['tax_nama'] . ' — ' . $row['nomor'],
                'kode_dokumen' => 'TAX',
            ], [
                [
                    'coa_id'      => $coaPajak,
                    'deskripsi'   => 'Pelunasan utang pajak ' . $row['tax_kode'],
                    'debit'       => (float) $row['nilai_pajak'],
                    'tax_type_id' => (int) $row['tax_type_id'],
                ],
                [
                    'coa_id'    => AccountMap::bankCoa($bankAccountId),
                    'deskripsi' => 'Penyetoran pajak ' . $row['nomor'],
                    'kredit'    => (float) $row['nilai_pajak'],
                ],
            ]);

            Db::exec(
                'UPDATE tax_documents SET status = ?, journal_id = ? WHERE id = ?',
                ['posted', $journalId, $id]
            );

            Audit::log('post', 'tax', [
                'ref_table'   => 'tax_documents',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Setor pajak ' . $row['nomor'] . ' sebesar ' . rupiah((float) $row['nilai_pajak']),
                'new'         => ['journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    /**
     * Posting dokumen pajak non-SSP (faktur/bukti potong) tanpa jurnal baru —
     * jurnal PPN/PPh sudah dibuat modul asal (AP/AR/biaya).
     */
    public static function posting(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pajak tidak ditemukan.');
        }
        if ((string) $row['tipe'] === 'ssp') {
            throw new \RuntimeException('SSP diproses lewat penyetoran, bukan posting.');
        }
        if ((string) $row['status'] !== 'draft') {
            throw new \RuntimeException('Hanya dokumen draft yang dapat diposting.');
        }

        Db::exec("UPDATE tax_documents SET status = 'posted' WHERE id = ?", [$id]);

        Audit::log('post', 'tax', [
            'ref_table'   => 'tax_documents',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Posting dokumen pajak ' . $row['nomor'],
        ]);
    }

    /** Tandai dokumen sudah dilaporkan ke DJP. */
    public static function lapor(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pajak tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'posted'], true)) {
            throw new \RuntimeException('Dokumen ini tidak dapat ditandai dilaporkan.');
        }

        Db::exec("UPDATE tax_documents SET status = 'reported' WHERE id = ?", [$id]);

        Audit::log('report', 'tax', [
            'ref_table'   => 'tax_documents',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tandai dokumen pajak ' . $row['nomor'] . ' sudah dilaporkan',
        ]);
    }

    public static function void(int $id, string $alasan = ''): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pajak tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            throw new \RuntimeException('Hanya dokumen draft yang dapat di-void.');
        }

        Db::exec("UPDATE tax_documents SET status = 'void' WHERE id = ?", [$id]);

        Audit::log('void', 'tax', [
            'ref_table'   => 'tax_documents',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Void dokumen pajak ' . $row['nomor'] . ($alasan !== '' ? ': ' . $alasan : ''),
        ]);
    }

    public static function hapus(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new \RuntimeException('Dokumen pajak tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft' || !empty($row['journal_id'])) {
            throw new \RuntimeException('Hanya dokumen draft tanpa jurnal yang dapat dihapus.');
        }

        Db::exec('DELETE FROM tax_documents WHERE id = ?', [$id]);
    }

    /**
     * Rekap dokumen pajak satu periode per tipe & jenis pajak.
     *
     * @return array{per_tipe:array<string,array<string,float|int>>,per_jenis:array<int,array<string,mixed>>}
     */
    public static function rekap(int $periodeId): array
    {
        $perTipe = [];
        foreach (self::TIPE as $kode => $label) {
            $perTipe[$kode] = ['dpp' => 0.0, 'pajak' => 0.0, 'jumlah' => 0];
        }

        foreach (Db::all(
            "SELECT tipe, COUNT(*) AS jumlah, COALESCE(SUM(dpp),0) AS dpp, COALESCE(SUM(nilai_pajak),0) AS pajak
             FROM tax_documents WHERE periode_id = ? AND status <> 'void'
             GROUP BY tipe",
            [$periodeId]
        ) as $row) {
            $perTipe[(string) $row['tipe']] = [
                'dpp'    => (float) $row['dpp'],
                'pajak'  => (float) $row['pajak'],
                'jumlah' => (int) $row['jumlah'],
            ];
        }

        $perJenis = Db::all(
            "SELECT tt.id, tt.kode, tt.nama, tt.jenis, tt.tarif,
                    COUNT(t.id) AS jumlah,
                    COALESCE(SUM(t.dpp),0) AS dpp,
                    COALESCE(SUM(t.nilai_pajak),0) AS pajak
             FROM tax_types tt
             LEFT JOIN tax_documents t ON t.tax_type_id = tt.id AND t.periode_id = ? AND t.status <> 'void'
             WHERE tt.is_active = 1
             GROUP BY tt.id, tt.kode, tt.nama, tt.jenis, tt.tarif
             ORDER BY tt.kode",
            [$periodeId]
        );

        return ['per_tipe' => $perTipe, 'per_jenis' => $perJenis];
    }

    /**
     * Hitung & simpan rekap SPT periode (upsert per periode + jenis).
     *
     * @return array<string,mixed> baris tax_reports
     */
    public static function generateReport(int $periodeId, string $jenis): array
    {
        if (!array_key_exists($jenis, self::REPORT_JENIS)) {
            throw new \RuntimeException('Jenis SPT tidak dikenal.');
        }

        $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        if ($periode === null) {
            throw new \RuntimeException('Periode akuntansi tidak ditemukan.');
        }

        if ($jenis === 'ppn') {
            $row = Db::one(
                "SELECT COALESCE(SUM(CASE WHEN tipe = 'faktur_keluaran' THEN dpp ELSE 0 END),0) AS dpp,
                        COALESCE(SUM(CASE WHEN tipe = 'faktur_keluaran' THEN nilai_pajak ELSE 0 END),0) AS pajak,
                        COALESCE(SUM(CASE WHEN tipe = 'faktur_masukan' THEN nilai_pajak ELSE 0 END),0) AS kredit
                 FROM tax_documents WHERE periode_id = ? AND status <> 'void'",
                [$periodeId]
            );

            $dpp = (float) $row['dpp'];
            $pajak = (float) $row['pajak'];
            $kredit = (float) $row['kredit'];
            $kurang = max(0.0, $pajak - $kredit);
        } else {
            $jenisPajak = $jenis === 'pph_badan' ? 'pph_final' : $jenis;
            $row = Db::one(
                "SELECT COALESCE(SUM(t.dpp),0) AS dpp, COALESCE(SUM(t.nilai_pajak),0) AS pajak
                 FROM tax_documents t
                 JOIN tax_types tt ON tt.id = t.tax_type_id
                 WHERE t.periode_id = ? AND t.status <> 'void' AND tt.jenis = ?
                   AND t.tipe IN ('bukti_potong','ssp')",
                [$periodeId, $jenisPajak]
            );

            $dpp = (float) $row['dpp'];
            $pajak = (float) $row['pajak'];
            $kredit = 0.0;
            $kurang = $pajak;
        }

        Db::exec(
            'INSERT INTO tax_reports (periode_id, jenis, total_dpp, total_pajak, total_kredit, kurang_bayar, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE total_dpp = VALUES(total_dpp), total_pajak = VALUES(total_pajak),
                total_kredit = VALUES(total_kredit), kurang_bayar = VALUES(kurang_bayar)',
            [
                $periodeId,
                $jenis,
                number_format($dpp, 2, '.', ''),
                number_format($pajak, 2, '.', ''),
                number_format($kredit, 2, '.', ''),
                number_format($kurang, 2, '.', ''),
                'draft',
                auth_id() ?: null,
            ]
        );

        Audit::log('create', 'tax', [
            'ref_table'   => 'tax_reports',
            'ref_id'      => $periodeId,
            'ref_code'    => 'SPT-' . $jenis . '/' . $periode['kode'],
            'description' => 'Hitung ' . self::REPORT_JENIS[$jenis] . ' ' . $periode['nama'],
            'new'         => ['dpp' => $dpp, 'pajak' => $pajak, 'kredit' => $kredit, 'kurang' => $kurang],
        ]);

        $report = Db::one(
            'SELECT * FROM tax_reports WHERE periode_id = ? AND jenis = ?',
            [$periodeId, $jenis]
        );

        return $report ?? [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function reports(?int $periodeId = null): array
    {
        $where = '1 = 1';
        $params = [];
        if ($periodeId !== null && $periodeId > 0) {
            $where .= ' AND r.periode_id = ?';
            $params[] = $periodeId;
        }

        return Db::all(
            'SELECT r.*, p.nama AS periode_nama, p.kode AS periode_kode, u.name AS created_by_name
             FROM tax_reports r
             LEFT JOIN fiscal_periods p ON p.id = r.periode_id
             LEFT JOIN app_users u ON u.id = r.created_by
             WHERE ' . $where . '
             ORDER BY p.tahun DESC, p.bulan DESC, r.jenis',
            $params
        );
    }

    public static function statusReport(int $id, string $status, ?string $tanggalLapor = null, string $referensi = ''): void
    {
        if (!in_array($status, ['draft', 'final', 'dilaporkan'], true)) {
            throw new \RuntimeException('Status SPT tidak dikenal.');
        }

        $row = Db::one('SELECT * FROM tax_reports WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Rekap SPT tidak ditemukan.');
        }
        if ($status === 'dilaporkan' && ($tanggalLapor === null || $tanggalLapor === '')) {
            throw new \RuntimeException('Tanggal lapor wajib diisi.');
        }

        Db::exec(
            'UPDATE tax_reports SET status = ?, tanggal_lapor = ?, referensi = ? WHERE id = ?',
            [
                $status,
                $status === 'dilaporkan' ? $tanggalLapor : null,
                $referensi !== '' ? mb_substr($referensi, 0, 80) : ($row['referensi'] ?? null),
                $id,
            ]
        );

        Audit::log('update', 'tax', [
            'ref_table'   => 'tax_reports',
            'ref_id'      => $id,
            'description' => 'Ubah status SPT menjadi ' . $status,
            'old'         => ['status' => $row['status']],
            'new'         => ['status' => $status],
        ]);
    }

    /**
     * Data e-Faktur (faktur keluaran) satu periode untuk ekspor CSV.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function efaktur(int $periodeId): array
    {
        return Db::all(
            "SELECT t.*, tt.kode AS tax_kode, p.nama AS partner_nama
             FROM tax_documents t
             JOIN tax_types tt ON tt.id = t.tax_type_id
             LEFT JOIN ms_partners p ON p.id = t.partner_id
             WHERE t.periode_id = ? AND t.tipe = 'faktur_keluaran' AND t.status <> 'void'
             ORDER BY t.tanggal, t.id",
            [$periodeId]
        );
    }

    /**
     * CSV siap impor e-Faktur (FK, NPWP, DPP, PPN).
     *
     * @return array{filename:string,content:string}
     */
    public static function efakturCsv(int $periodeId): array
    {
        $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        if ($periode === null) {
            throw new \RuntimeException('Periode akuntansi tidak ditemukan.');
        }

        $baris = [];
        $baris[] = ['FK', 'NPWP', 'Nama', 'Tanggal', 'DPP', 'Tarif', 'PPN', 'Referensi'];

        foreach (self::efaktur($periodeId) as $row) {
            $baris[] = [
                (string) $row['nomor'],
                (string) ($row['npwp'] ?? ''),
                (string) ($row['partner_nama'] ?? ''),
                (string) $row['tanggal'],
                number_format((float) $row['dpp'], 2, '.', ''),
                number_format((float) $row['tarif'], 2, '.', ''),
                number_format((float) $row['nilai_pajak'], 2, '.', ''),
                (string) ($row['ref_module'] !== null ? $row['ref_module'] . '#' . $row['ref_id'] : ''),
            ];
        }

        $out = '';
        foreach ($baris as $line) {
            $out .= implode(';', array_map(static fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $line)) . "\r\n";
        }

        return [
            'filename' => 'efaktur-keluaran-' . $periode['kode'] . '.csv',
            'content'  => $out,
        ];
    }
}
