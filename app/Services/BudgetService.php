<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * RKAP & pengendalian anggaran: penyusunan, revisi, aktivasi versi,
 * monitoring realisasi vs pagu, komitmen PO, dan pemeriksaan batas anggaran.
 *
 * Realisasi dihitung langsung dari `journal_lines` berstatus posted (bukan cache),
 * sehingga selalu konsisten dengan buku besar.
 */
final class BudgetService
{
    public const STATUS = [
        'draft'     => 'Draft',
        'submitted' => 'Diajukan',
        'approved'  => 'Disetujui',
        'rejected'  => 'Ditolak',
        'aktif'     => 'Aktif',
        'arsip'     => 'Arsip',
    ];

    public const JENIS = [
        'pendapatan' => 'Pendapatan',
        'beban'      => 'Beban',
    ];

    public const COMMIT_STATUS = [
        'open'      => 'Terbuka',
        'sebagian'  => 'Sebagian',
        'closed'    => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT b.*, u.name AS created_by_name, a.name AS approved_by_name,
                    p.nomor AS parent_nomor,
                    (SELECT COUNT(*) FROM budget_lines bl WHERE bl.budget_id = b.id) AS jumlah_baris
             FROM budgets b
             LEFT JOIN app_users u ON u.id = b.created_by
             LEFT JOIN app_users a ON a.id = b.approved_by
             LEFT JOIN budgets p ON p.id = b.parent_id
             WHERE b.id = ?',
            [$id]
        );
    }

    /** RKAP aktif pada tahun tertentu. @return array<string,mixed>|null */
    public static function aktif(int $tahun): ?array
    {
        return Db::one(
            "SELECT * FROM budgets WHERE tahun = ? AND status = 'aktif' ORDER BY versi DESC, id DESC LIMIT 1",
            [$tahun]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function lines(int $id): array
    {
        return Db::all(
            'SELECT bl.*, c.kode AS coa_kode, c.nama AS coa_nama, c.tipe AS coa_tipe,
                    bd.nama AS bidang_nama, dv.nama AS divisi_nama, bu.nama AS business_unit_nama,
                    cc.nama AS cost_center_nama, pr.nama AS project_nama
             FROM budget_lines bl
             JOIN coa_accounts c ON c.id = bl.coa_id
             LEFT JOIN ms_bidang bd ON bd.id = bl.bidang_id
             LEFT JOIN ms_divisi dv ON dv.id = bl.divisi_id
             LEFT JOIN ms_business_units bu ON bu.id = bl.business_unit_id
             LEFT JOIN ms_cost_centers cc ON cc.id = bl.cost_center_id
             LEFT JOIN ms_projects pr ON pr.id = bl.project_id
             WHERE bl.budget_id = ?
             ORDER BY bl.jenis DESC, c.kode, bl.id',
            [$id]
        );
    }

    /** Sebar nilai tahunan ke 12 bulan (pembulatan sisa ke Desember). */
    private static function sebarBulanan(float $total): array
    {
        $bulanan = [];
        $perBulan = round($total / 12, 2);
        $akumulasi = 0.0;
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            if ($bulan === 12) {
                $bulanan[$bulan] = round($total - $akumulasi, 2);
            } else {
                $bulanan[$bulan] = $perBulan;
                $akumulasi += $perBulan;
            }
        }
        return $bulanan;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasi(array $input): array
    {
        $tahun = (int) ($input['tahun'] ?? date('Y'));
        $lines = [];

        foreach ((array) ($input['lines'] ?? []) as $line) {
            $coaId = (int) ($line['coa_id'] ?? 0);
            $total = JournalService::uang($line['total'] ?? 0);

            if ($coaId <= 0 && $total <= 0) {
                continue;
            }

            $jenis = (string) ($line['jenis'] ?? 'beban');
            if ($coaId > 0) {
                $tipe = (string) Db::val('SELECT tipe FROM coa_accounts WHERE id = ?', [$coaId]);
                if (in_array($tipe, ['pendapatan', 'beban'], true)) {
                    $jenis = $tipe;
                }
            }
            if (!array_key_exists($jenis, self::JENIS)) {
                $jenis = 'beban';
            }

            $lines[] = [
                'coa_id'           => $coaId,
                'jenis'            => $jenis,
                'bidang_id'        => (int) ($line['bidang_id'] ?? 0) ?: null,
                'divisi_id'        => (int) ($line['divisi_id'] ?? 0) ?: null,
                'business_unit_id' => (int) ($line['business_unit_id'] ?? 0) ?: null,
                'cost_center_id'   => (int) ($line['cost_center_id'] ?? 0) ?: null,
                'project_id'       => (int) ($line['project_id'] ?? 0) ?: null,
                'total'            => $total,
                'catatan'          => trim((string) ($line['catatan'] ?? '')),
            ];
        }

        return [
            'tahun'   => $tahun,
            'judul'   => trim((string) ($input['judul'] ?? '')),
            'catatan' => trim((string) ($input['catatan'] ?? '')),
            'lines'   => $lines,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        if ($data['tahun'] < 2000 || $data['tahun'] > 2100) {
            $errors['tahun'] = 'Tahun anggaran tidak valid.';
        }
        if ($data['judul'] === '') {
            $errors['judul'] = 'Judul RKAP wajib diisi.';
        }
        if ($data['lines'] === []) {
            $errors['lines'] = 'Minimal satu baris anggaran harus diisi.';
            return $errors;
        }

        $kombinasi = [];
        foreach ($data['lines'] as $index => $line) {
            $nomor = $index + 1;
            if ($line['coa_id'] <= 0) {
                $errors['lines'] = 'Baris #' . $nomor . ': akun COA wajib dipilih.';
                break;
            }
            $akun = Db::one('SELECT tipe, allow_posting, is_active FROM coa_accounts WHERE id = ?', [$line['coa_id']]);
            if ($akun === null) {
                $errors['lines'] = 'Baris #' . $nomor . ': akun COA tidak ditemukan.';
                break;
            }
            if ((int) $akun['allow_posting'] !== 1 || (int) $akun['is_active'] !== 1) {
                $errors['lines'] = 'Baris #' . $nomor . ': akun tidak boleh menerima posting / tidak aktif.';
                break;
            }
            if ((string) $akun['tipe'] !== $line['jenis']) {
                $errors['lines'] = 'Baris #' . $nomor . ': akun ' . $akun['tipe'] . ' tidak sesuai jenis ' . $line['jenis'] . '.';
                break;
            }
            if ($line['total'] <= 0) {
                $errors['lines'] = 'Baris #' . $nomor . ': nilai pagu harus lebih besar dari nol.';
                break;
            }

            $kunci = implode('|', [
                $line['coa_id'], $line['bidang_id'] ?? 0, $line['divisi_id'] ?? 0,
                $line['business_unit_id'] ?? 0, $line['cost_center_id'] ?? 0, $line['project_id'] ?? 0,
            ]);
            if (isset($kombinasi[$kunci])) {
                $errors['lines'] = 'Baris #' . $nomor . ': kombinasi akun & dimensi sama dengan baris #' . $kombinasi[$kunci] . '.';
                break;
            }
            $kombinasi[$kunci] = $nomor;
        }

        return $errors;
    }

    /**
     * Simpan RKAP (header + baris). Nomor baru memakai `doc_sequences` RKAP.
     *
     * @param array<string,mixed> $data
     */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $totalPendapatan = 0.0;
            $totalBeban = 0.0;
            foreach ($data['lines'] as $line) {
                if ($line['jenis'] === 'pendapatan') {
                    $totalPendapatan += $line['total'];
                } else {
                    $totalBeban += $line['total'];
                }
            }

            $payload = [
                'tahun'            => (int) $data['tahun'],
                'judul'            => mb_substr((string) $data['judul'], 0, 150),
                'catatan'          => $data['catatan'] !== '' ? mb_substr((string) $data['catatan'], 0, 255) : null,
                'total_pendapatan' => number_format(round($totalPendapatan, 2), 2, '.', ''),
                'total_beban'      => number_format(round($totalBeban, 2), 2, '.', ''),
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next('RKAP', (int) $data['tahun'] . '-01-01');
                $payload['versi'] = 1;
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('budgets', $payload);
            } else {
                Db::update('budgets', $payload, 'id = :id', [':id' => $id]);
            }

            Db::exec('DELETE FROM budget_lines WHERE budget_id = ?', [$id]);

            foreach ($data['lines'] as $line) {
                $bulanan = self::sebarBulanan((float) $line['total']);
                $baris = [
                    'budget_id'        => $id,
                    'coa_id'           => (int) $line['coa_id'],
                    'jenis'            => $line['jenis'],
                    'bidang_id'        => $line['bidang_id'],
                    'divisi_id'        => $line['divisi_id'],
                    'business_unit_id' => $line['business_unit_id'],
                    'cost_center_id'   => $line['cost_center_id'],
                    'project_id'       => $line['project_id'],
                    'total'            => number_format((float) $line['total'], 2, '.', ''),
                    'catatan'          => $line['catatan'] !== '' ? mb_substr((string) $line['catatan'], 0, 255) : null,
                ];
                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $baris[sprintf('m%02d', $bulan)] = number_format($bulanan[$bulan], 2, '.', '');
                }
                Db::insert('budget_lines', $baris);
            }

            return $id;
        });
    }

    public static function ajukan(int $id): void
    {
        $row = Db::one('SELECT * FROM budgets WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('RKAP tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Hanya draft/ditolak yang dapat diajukan.');
        }
        Db::exec('UPDATE budgets SET status = ?, approved_by = NULL, approved_at = NULL WHERE id = ?', ['submitted', $id]);
    }

    public static function setujui(int $id): void
    {
        $row = Db::one('SELECT * FROM budgets WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('RKAP tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya RKAP yang diajukan dapat disetujui.');
        }
        Db::exec('UPDATE budgets SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?', [
            'approved', auth_id() ?: null, now(), $id,
        ]);
    }

    public static function tolak(int $id, string $alasan = ''): void
    {
        $row = Db::one('SELECT nomor, status FROM budgets WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('RKAP tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya RKAP yang diajukan dapat ditolak.');
        }
        Db::exec('UPDATE budgets SET status = ? WHERE id = ?', ['rejected', $id]);

        Audit::log('reject', 'budget', [
            'ref_table'   => 'budgets',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tolak RKAP: ' . ($alasan !== '' ? $alasan : 'tanpa alasan'),
        ]);
    }

    /** Aktifkan versi RKAP; versi aktif lain pada tahun yang sama diarsipkan. */
    public static function aktifkan(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            $row = Db::one('SELECT * FROM budgets WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('RKAP tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new \RuntimeException('RKAP harus disetujui sebelum diaktifkan.');
            }

            Db::exec(
                "UPDATE budgets SET status = 'arsip' WHERE tahun = ? AND status = 'aktif' AND id <> ?",
                [(int) $row['tahun'], $id]
            );
            Db::exec('UPDATE budgets SET status = ? WHERE id = ?', ['aktif', $id]);

            Audit::log('activate', 'budget', [
                'ref_table'   => 'budgets',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Aktifkan RKAP ' . $row['nomor'] . ' sebagai pagu tahun ' . $row['tahun'],
            ]);
        });
    }

    public static function arsipkan(int $id): void
    {
        $row = Db::one('SELECT status FROM budgets WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('RKAP tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'aktif') {
            throw new \RuntimeException('Hanya RKAP aktif yang dapat diarsipkan.');
        }
        Db::exec('UPDATE budgets SET status = ? WHERE id = ?', ['arsip', $id]);
    }

    /** Buat versi revisi dari RKAP tertentu (salin seluruh baris). */
    public static function revisi(int $id): int
    {
        return Db::transaction(static function () use ($id): int {
            $row = Db::one('SELECT * FROM budgets WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('RKAP tidak ditemukan.');
            }

            $versiBaru = (int) Db::val('SELECT COALESCE(MAX(versi),0) + 1 FROM budgets WHERE tahun = ?', [(int) $row['tahun']]);
            $nomor = 'RKAP-REV-' . sprintf('%02d', $versiBaru) . '/' . (int) $row['tahun'];

            $baru = Db::insert('budgets', [
                'nomor'            => $nomor,
                'tahun'            => (int) $row['tahun'],
                'versi'            => $versiBaru,
                'parent_id'        => $id,
                'judul'            => mb_substr('Revisi ' . $versiBaru . ' — ' . (string) $row['judul'], 0, 150),
                'status'           => 'draft',
                'total_pendapatan' => $row['total_pendapatan'],
                'total_beban'      => $row['total_beban'],
                'catatan'          => 'Revisi dari ' . $row['nomor'],
                'created_by'       => auth_id() ?: null,
            ]);

            Db::exec(
                'INSERT INTO budget_lines (budget_id, coa_id, jenis, bidang_id, divisi_id, business_unit_id,
                    cost_center_id, project_id, m01, m02, m03, m04, m05, m06, m07, m08, m09, m10, m11, m12, total, catatan)
                 SELECT ?, coa_id, jenis, bidang_id, divisi_id, business_unit_id,
                    cost_center_id, project_id, m01, m02, m03, m04, m05, m06, m07, m08, m09, m10, m11, m12, total, catatan
                 FROM budget_lines WHERE budget_id = ?',
                [$baru, $id]
            );

            Audit::log('create', 'budget', [
                'ref_table'   => 'budgets',
                'ref_id'      => $baru,
                'ref_code'    => $nomor,
                'description' => 'Buat revisi RKAP dari ' . $row['nomor'],
            ]);

            return $baru;
        });
    }

    public static function hapus(int $id): void
    {
        $row = Db::one('SELECT status FROM budgets WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('RKAP tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            throw new \RuntimeException('RKAP yang sudah diajukan tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM budgets WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed> */
    public static function ringkasan(int $tahun): array
    {
        $row = Db::one(
            'SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(total_pendapatan),0) AS pendapatan,
                    COALESCE(SUM(total_beban),0) AS beban
             FROM budgets WHERE tahun = ?',
            [$tahun]
        );
        $aktif = self::aktif($tahun);

        return [
            'jumlah'     => (int) ($row['jumlah'] ?? 0),
            'pendapatan' => (float) ($row['pendapatan'] ?? 0),
            'beban'      => (float) ($row['beban'] ?? 0),
            'aktif_id'   => $aktif['id'] ?? null,
            'aktif_nomor' => $aktif['nomor'] ?? null,
        ];
    }

    /**
     * Realisasi per akun + divisi dari jurnal posted.
     *
     * @return array<string,float> kunci "coaId|divisiId"
     */
    private static function realisasiMap(int $tahun, int $sampaiBulan): array
    {
        $rows = Db::all(
            "SELECT jl.coa_id, COALESCE(jl.divisi_id, 0) AS dimensi_divisi,
                    SUM(CASE WHEN c.tipe = 'pendapatan' THEN jl.kredit - jl.debit ELSE jl.debit - jl.kredit END) AS nilai
             FROM journal_lines jl
             JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?
             JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe IN ('pendapatan','beban')
             WHERE MONTH(j.tanggal) <= ?
             GROUP BY jl.coa_id, COALESCE(jl.divisi_id, 0)",
            [$tahun, $sampaiBulan]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['coa_id'] . '|' . (int) $row['dimensi_divisi']] = (float) $row['nilai'];
        }
        return $map;
    }

    /**
     * Komitmen per akun + divisi.
     *
     * @return array<string,float>
     */
    /**
     * Baris realisasi per akun & dimensi (belum dialokasikan ke baris RKAP).
     *
     * @return array<int,array<string,mixed>>
     */
    private static function realisasiRows(int $tahun, int $sampaiBulan): array
    {
        return Db::all(
            "SELECT jl.coa_id, jl.bidang_id, jl.divisi_id, jl.cost_center_id, jl.project_id,
                    SUM(CASE WHEN c.tipe = 'pendapatan' THEN jl.kredit - jl.debit ELSE jl.debit - jl.kredit END) AS nilai
             FROM journal_lines jl
             JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?
             JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe IN ('pendapatan','beban')
             WHERE MONTH(j.tanggal) <= ?
             GROUP BY jl.coa_id, jl.bidang_id, jl.divisi_id, jl.cost_center_id, jl.project_id",
            [$tahun, $sampaiBulan]
        );
    }

    /** @return array<int,array<string,mixed>> */
    private static function komitmenRows(int $budgetId): array
    {
        return Db::all(
            "SELECT coa_id, bidang_id, divisi_id, cost_center_id, project_id, SUM(nilai) AS nilai
             FROM budget_commitments
             WHERE status IN ('open','sebagian') AND (budget_id = ? OR budget_id IS NULL)
             GROUP BY coa_id, bidang_id, divisi_id, cost_center_id, project_id",
            [$budgetId]
        );
    }

    /**
     * Alokasikan nilai realisasi/komitmen ke baris RKAP tanpa perhitungan ganda.
     *
     * Baris dengan cost center/proyek spesifik mengambil nilai yang dimensinya
     * persis sama. Baris tanpa dimensi rinci mengambil sisa pagu dimensi
     * (akun + divisi) setelah baris spesifik dikonsumsi.
     *
     * @param array<int,array<string,mixed>> $lines
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,float> nilai per id baris
     */
    private static function alokasi(array $lines, array $rows): array
    {
        $dimensi = static fn (array $row, string $key): int => (int) ($row[$key] ?? 0);

        $pool = [];
        $detail = [];
        foreach ($rows as $row) {
            $nilai = (float) $row['nilai'];
            $kunci = $dimensi($row, 'coa_id') . '|' . $dimensi($row, 'divisi_id');
            $penuh = $kunci . '|' . $dimensi($row, 'bidang_id') . '|'
                . $dimensi($row, 'cost_center_id') . '|' . $dimensi($row, 'project_id');

            $pool[$kunci] = ($pool[$kunci] ?? 0.0) + $nilai;
            $detail[$penuh] = ($detail[$penuh] ?? 0.0) + $nilai;
        }

        $terurut = $lines;
        usort($terurut, static function (array $a, array $b): int {
            $spesifik = static fn (array $l): int => ((int) ($l['cost_center_id'] ?? 0) > 0 ? 1 : 0)
                + ((int) ($l['project_id'] ?? 0) > 0 ? 1 : 0);
            return [$spesifik($b), (int) $b['id']] <=> [$spesifik($a), (int) $a['id']];
        });

        $hasil = [];
        foreach ($terurut as $line) {
            $kunci = (int) $line['coa_id'] . '|' . (int) ($line['divisi_id'] ?? 0);
            $penuh = $kunci . '|' . (int) ($line['bidang_id'] ?? 0) . '|'
                . (int) ($line['cost_center_id'] ?? 0) . '|' . (int) ($line['project_id'] ?? 0);

            $khusus = (int) ($line['cost_center_id'] ?? 0) > 0 || (int) ($line['project_id'] ?? 0) > 0;
            $ambil = 0.0;

            if ($khusus) {
                if (!empty($detail[$penuh])) {
                    $ambil = min((float) $detail[$penuh], (float) ($pool[$kunci] ?? 0.0));
                    unset($detail[$penuh]);
                }
            } else {
                $ambil = max(0.0, (float) ($pool[$kunci] ?? 0.0));
            }

            $pool[$kunci] = max(0.0, (float) ($pool[$kunci] ?? 0.0) - $ambil);
            $hasil[(int) $line['id']] = round($ambil, 2);
        }

        return $hasil;
    }

    /**
     * Monitoring pagu vs realisasi vs komitmen per baris RKAP.
     *
     * @return array{rows:array<int,array<string,mixed>>,totals:array<string,mixed>}
     */
    public static function monitoring(int $budgetId, ?int $sampaiBulan = null): array
    {
        $budget = self::find($budgetId);
        if ($budget === null) {
            return ['rows' => [], 'totals' => []];
        }

        $sampaiBulan = $sampaiBulan === null ? 12 : max(1, min(12, $sampaiBulan));
        $lines = self::lines($budgetId);
        $realisasi = self::alokasi($lines, self::realisasiRows((int) $budget['tahun'], $sampaiBulan));
        $komitmen = self::alokasi($lines, self::komitmenRows($budgetId));
        $alert = (float) setting('budget_alert_percent', 80);

        $rows = [];
        $totals = [
            'pagu_pendapatan' => 0.0,
            'pagu_beban' => 0.0,
            'realisasi_pendapatan' => 0.0,
            'realisasi_beban' => 0.0,
            'komitmen_beban' => 0.0,
            'jumlah_over' => 0,
            'jumlah_waspada' => 0,
            'alert' => $alert,
        ];

        foreach ($lines as $line) {
            $pagu = (float) $line['total'];
            $real = (float) ($realisasi[(int) $line['id']] ?? 0.0);
            $kom = $line['jenis'] === 'beban' ? (float) ($komitmen[(int) $line['id']] ?? 0.0) : 0.0;
            $persen = $pagu > 0 ? ($real / $pagu) * 100 : 0.0;

            if ($line['jenis'] === 'pendapatan') {
                $totals['pagu_pendapatan'] += $pagu;
                $totals['realisasi_pendapatan'] += $real;
            } else {
                $totals['pagu_beban'] += $pagu;
                $totals['realisasi_beban'] += $real;
                $totals['komitmen_beban'] += $kom;
            }

            if ($persen >= 100) {
                $totals['jumlah_over']++;
            } elseif ($persen >= $alert) {
                $totals['jumlah_waspada']++;
            }

            $rows[] = [
                'id'          => (int) $line['id'],
                'coa_id'      => (int) $line['coa_id'],
                'coa_kode'    => (string) $line['coa_kode'],
                'coa_nama'    => (string) $line['coa_nama'],
                'jenis'       => (string) $line['jenis'],
                'dimensi'     => trim(implode(' · ', array_filter([
                    $line['divisi_nama'], $line['bidang_nama'], $line['business_unit_nama'],
                    $line['cost_center_nama'], $line['project_nama'],
                ]))),
                'pagu'        => $pagu,
                'realisasi'   => $real,
                'komitmen'    => $kom,
                'sisa'        => $pagu - $real - $kom,
                'persen'      => $persen,
                'status'      => $persen >= 100 ? 'over' : ($persen >= $alert ? 'waspada' : 'aman'),
            ];
        }

        $totals['persen_beban'] = $totals['pagu_beban'] > 0
            ? (($totals['realisasi_beban'] + $totals['komitmen_beban']) / $totals['pagu_beban']) * 100
            : 0.0;
        $totals['persen_pendapatan'] = $totals['pagu_pendapatan'] > 0
            ? ($totals['realisasi_pendapatan'] / $totals['pagu_pendapatan']) * 100
            : 0.0;
        $totals['serapan'] = $totals['persen_beban'];
        $totals['sisa_beban'] = $totals['pagu_beban'] - $totals['realisasi_beban'] - $totals['komitmen_beban'];

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Realisasi vs pagu per divisi (untuk grafik batang monitoring).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function monitoringDivisi(int $tahun, ?int $sampaiBulan = null): array
    {
        $sampaiBulan = $sampaiBulan === null ? (int) date('n') : max(1, min(12, $sampaiBulan));
        $budget = self::aktif($tahun);
        if ($budget === null) {
            return [];
        }

        $pagu = [];
        foreach (Db::all(
            "SELECT COALESCE(divisi_id,0) AS divisi_id, SUM(total) AS nilai
             FROM budget_lines WHERE budget_id = ? AND jenis = 'beban'
             GROUP BY COALESCE(divisi_id,0)",
            [(int) $budget['id']]
        ) as $row) {
            $pagu[(int) $row['divisi_id']] = (float) $row['nilai'];
        }

        $realisasi = [];
        foreach (Db::all(
            "SELECT COALESCE(jl.divisi_id,0) AS divisi_id, SUM(jl.debit - jl.kredit) AS nilai
             FROM journal_lines jl
             JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?
             JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe = 'beban'
             WHERE MONTH(j.tanggal) <= ?
             GROUP BY COALESCE(jl.divisi_id,0)",
            [$tahun, $sampaiBulan]
        ) as $row) {
            $realisasi[(int) $row['divisi_id']] = (float) $row['nilai'];
        }

        $hasil = [];
        foreach (Db::all('SELECT id, kode, nama FROM ms_divisi WHERE is_active = 1 ORDER BY nama') as $divisi) {
            $id = (int) $divisi['id'];
            $nilaiPagu = $pagu[$id] ?? 0.0;
            $nilaiRealisasi = $realisasi[$id] ?? 0.0;
            if ($nilaiPagu <= 0 && $nilaiRealisasi <= 0) {
                continue;
            }
            $hasil[] = [
                'id'        => $id,
                'kode'      => (string) $divisi['kode'],
                'nama'      => (string) $divisi['nama'],
                'pagu'      => $nilaiPagu,
                'realisasi' => $nilaiRealisasi,
                'sisa'      => $nilaiPagu - $nilaiRealisasi,
                'persen'    => $nilaiPagu > 0 ? ($nilaiRealisasi / $nilaiPagu) * 100 : 0.0,
            ];
        }

        usort($hasil, static fn ($a, $b) => $b['pagu'] <=> $a['pagu']);
        return $hasil;
    }

    /**
     * Analisis cost center & profit center: pagu vs realisasi per pusat
     * pertanggungjawaban (kolom `ms_cost_centers.tipe`).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function analisisCostCenter(int $tahun, ?int $sampaiBulan = null): array
    {
        $sampaiBulan = $sampaiBulan === null ? (int) date('n') : max(1, min(12, $sampaiBulan));
        $budget = self::aktif($tahun);
        if ($budget === null) {
            return [];
        }

        $pagu = [];
        foreach (Db::all(
            "SELECT COALESCE(cost_center_id,0) AS cc_id, SUM(total) AS nilai
             FROM budget_lines WHERE budget_id = ? AND jenis = 'beban'
             GROUP BY COALESCE(cost_center_id,0)",
            [(int) $budget['id']]
        ) as $row) {
            $pagu[(int) $row['cc_id']] = (float) $row['nilai'];
        }

        $realisasi = [];
        foreach (Db::all(
            "SELECT COALESCE(jl.cost_center_id,0) AS cc_id, SUM(jl.debit - jl.kredit) AS nilai
             FROM journal_lines jl
             JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?
             JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe = 'beban'
             WHERE MONTH(j.tanggal) <= ?
             GROUP BY COALESCE(jl.cost_center_id,0)",
            [$tahun, $sampaiBulan]
        ) as $row) {
            $realisasi[(int) $row['cc_id']] = (float) $row['nilai'];
        }

        $hasil = [];
        foreach (Db::all('SELECT id, kode, nama, tipe FROM ms_cost_centers WHERE is_active = 1 ORDER BY kode') as $cc) {
            $id = (int) $cc['id'];
            $nilaiPagu = $pagu[$id] ?? 0.0;
            $nilaiRealisasi = $realisasi[$id] ?? 0.0;
            if ($nilaiPagu <= 0 && $nilaiRealisasi <= 0) {
                continue;
            }
            $hasil[] = [
                'id'        => $id,
                'kode'      => (string) $cc['kode'],
                'nama'      => (string) $cc['nama'],
                'tipe'      => (string) $cc['tipe'],
                'pagu'      => $nilaiPagu,
                'realisasi' => $nilaiRealisasi,
                'sisa'      => $nilaiPagu - $nilaiRealisasi,
                'persen'    => $nilaiPagu > 0 ? ($nilaiRealisasi / $nilaiPagu) * 100 : 0.0,
            ];
        }

        usort($hasil, static fn ($a, $b) => [$b['pagu'], $a['kode']] <=> [$a['pagu'], $b['kode']]);
        return $hasil;
    }

    /**
     * Analisis profit center per unit usaha: pendapatan vs beban (pagu & realisasi).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function analisisProfitCenter(int $tahun, ?int $sampaiBulan = null): array
    {
        $sampaiBulan = $sampaiBulan === null ? (int) date('n') : max(1, min(12, $sampaiBulan));
        $budget = self::aktif($tahun);
        if ($budget === null) {
            return [];
        }

        $pagu = ['pendapatan' => [], 'beban' => []];
        foreach (Db::all(
            "SELECT COALESCE(business_unit_id,0) AS bu_id, jenis, SUM(total) AS nilai
             FROM budget_lines WHERE budget_id = ?
             GROUP BY COALESCE(business_unit_id,0), jenis",
            [(int) $budget['id']]
        ) as $row) {
            $pagu[(string) $row['jenis']][(int) $row['bu_id']] = (float) $row['nilai'];
        }

        $realisasi = ['pendapatan' => [], 'beban' => []];
        foreach (Db::all(
            "SELECT COALESCE(jl.business_unit_id,0) AS bu_id, c.tipe,
                    SUM(CASE WHEN c.tipe = 'pendapatan' THEN jl.kredit - jl.debit ELSE jl.debit - jl.kredit END) AS nilai
             FROM journal_lines jl
             JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?
             JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe IN ('pendapatan','beban')
             WHERE MONTH(j.tanggal) <= ?
             GROUP BY COALESCE(jl.business_unit_id,0), c.tipe",
            [$tahun, $sampaiBulan]
        ) as $row) {
            $realisasi[(string) $row['tipe']][(int) $row['bu_id']] = (float) $row['nilai'];
        }

        $hasil = [];
        foreach (Db::all('SELECT id, kode, nama FROM ms_business_units WHERE is_active = 1 ORDER BY nama') as $unit) {
            $id = (int) $unit['id'];
            $paguPendapatan = $pagu['pendapatan'][$id] ?? 0.0;
            $paguBeban = $pagu['beban'][$id] ?? 0.0;
            $realPendapatan = $realisasi['pendapatan'][$id] ?? 0.0;
            $realBeban = $realisasi['beban'][$id] ?? 0.0;

            if ($paguPendapatan <= 0 && $paguBeban <= 0 && $realPendapatan <= 0 && $realBeban <= 0) {
                continue;
            }

            $hasil[] = [
                'id'             => $id,
                'kode'           => (string) $unit['kode'],
                'nama'           => (string) $unit['nama'],
                'pagu_pendapatan' => $paguPendapatan,
                'pagu_beban'     => $paguBeban,
                'real_pendapatan' => $realPendapatan,
                'real_beban'     => $realBeban,
                'pagu_margin'    => $paguPendapatan - $paguBeban,
                'real_margin'    => $realPendapatan - $realBeban,
            ];
        }

        usort($hasil, static fn ($a, $b) => abs($b['pagu_pendapatan']) <=> abs($a['pagu_pendapatan']));
        return $hasil;
    }

    /* ------------------------------------------------------------------ komitmen */

    /** @return array<int,array<string,mixed>> */
    public static function commitments(int $budgetId = 0, string $status = '', string $q = '', int $tahun = 0): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($budgetId > 0) {
            $where[] = 'k.budget_id = ?';
            $params[] = $budgetId;
        }
        if ($status !== '' && array_key_exists($status, self::COMMIT_STATUS)) {
            $where[] = 'k.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(k.nomor_po LIKE ? OR k.keterangan LIKE ? OR p.nama LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($tahun > 0) {
            $where[] = 'YEAR(k.tanggal) = ?';
            $params[] = $tahun;
        }

        return Db::all(
            'SELECT k.*, p.nama AS partner_nama, c.kode AS coa_kode, c.nama AS coa_nama,
                    b.nomor AS budget_nomor, pr.nama AS project_nama, dv.nama AS divisi_nama,
                    u.name AS created_by_name
             FROM budget_commitments k
             LEFT JOIN ms_partners p ON p.id = k.partner_id
             LEFT JOIN coa_accounts c ON c.id = k.coa_id
             LEFT JOIN budgets b ON b.id = k.budget_id
             LEFT JOIN ms_projects pr ON pr.id = k.project_id
             LEFT JOIN ms_divisi dv ON dv.id = k.divisi_id
             LEFT JOIN app_users u ON u.id = k.created_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY k.tanggal DESC, k.id DESC
             LIMIT 300',
            $params
        );
    }

    /** @return array<string,mixed> */
    public static function commitmentRingkasan(int $tahun = 0): array
    {
        $tahun = $tahun > 0 ? $tahun : (int) date('Y');
        $row = Db::one(
            "SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(nilai),0) AS total,
                    COALESCE(SUM(CASE WHEN status = 'open' THEN nilai ELSE 0 END),0) AS status_open,
                    COALESCE(SUM(CASE WHEN status = 'sebagian' THEN nilai ELSE 0 END),0) AS sebagian,
                    COALESCE(SUM(CASE WHEN status IN ('open','sebagian') THEN nilai ELSE 0 END),0) AS terbuka,
                    COALESCE(SUM(CASE WHEN status = 'closed' THEN nilai ELSE 0 END),0) AS selesai,
                    COALESCE(SUM(CASE WHEN status = 'cancelled' THEN nilai ELSE 0 END),0) AS batal
             FROM budget_commitments WHERE YEAR(tanggal) = ?",
            [$tahun]
        );

        return [
            'jumlah'  => (int) ($row['jumlah'] ?? 0),
            'total'   => (float) ($row['total'] ?? 0),
            'terbuka' => (float) ($row['terbuka'] ?? 0),
            'sebagian' => (float) ($row['sebagian'] ?? 0),
            'selesai' => (float) ($row['selesai'] ?? 0),
            'batal'   => (float) ($row['batal'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $input @return array{q:array<string,mixed>,errors:array<string,string>} */
    public static function normalisasiCommitment(array $input): array
    {
        $data = [
            'nomor_po'       => trim((string) ($input['nomor_po'] ?? '')),
            'budget_id'      => (int) ($input['budget_id'] ?? 0) ?: null,
            'partner_id'     => (int) ($input['partner_id'] ?? 0) ?: null,
            'coa_id'         => (int) ($input['coa_id'] ?? 0) ?: null,
            'project_id'     => (int) ($input['project_id'] ?? 0) ?: null,
            'bidang_id'      => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'      => (int) ($input['divisi_id'] ?? 0) ?: null,
            'cost_center_id' => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'tanggal'        => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'nilai'          => JournalService::uang($input['nilai'] ?? 0),
            'status'         => (string) ($input['status'] ?? 'open'),
            'keterangan'     => trim((string) ($input['keterangan'] ?? '')),
        ];

        if (!array_key_exists($data['status'], self::COMMIT_STATUS)) {
            $data['status'] = 'open';
        }

        $errors = [];
        if ($data['nomor_po'] === '') {
            $errors['nomor_po'] = 'Nomor PO/kontrak pengadaan wajib diisi.';
        } elseif ((int) Db::val('SELECT COUNT(*) FROM budget_commitments WHERE nomor_po = ? AND id <> ?', [$data['nomor_po'], (int) ($input['id'] ?? 0)]) > 0) {
            $errors['nomor_po'] = 'Nomor PO sudah dipakai.';
        }
        if ($data['nilai'] <= 0) {
            $errors['nilai'] = 'Nilai komitmen harus lebih besar dari nol.';
        }
        if (strtotime($data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal komitmen tidak valid.';
        }
        if ($data['coa_id'] === null) {
            $errors['coa_id'] = 'Akun beban wajib dipilih.';
        } else {
            $akun = Db::one('SELECT tipe, allow_posting, is_active FROM coa_accounts WHERE id = ?', [$data['coa_id']]);
            if ($akun === null) {
                $errors['coa_id'] = 'Akun COA tidak ditemukan.';
            } elseif ((string) $akun['tipe'] !== 'beban') {
                $errors['coa_id'] = 'Komitmen pengadaan hanya untuk akun beban.';
            } elseif ((int) $akun['allow_posting'] !== 1 || (int) $akun['is_active'] !== 1) {
                $errors['coa_id'] = 'Akun tidak aktif / tidak boleh menerima posting.';
            }
        }

        return ['q' => $data, 'errors' => $errors];
    }

    /** @param array<string,mixed> $data */
    public static function simpanCommitment(array $data): int
    {
        return Db::insert('budget_commitments', [
            'nomor_po'       => mb_substr((string) $data['nomor_po'], 0, 50),
            'budget_id'      => $data['budget_id'],
            'partner_id'     => $data['partner_id'],
            'coa_id'         => $data['coa_id'],
            'project_id'     => $data['project_id'],
            'bidang_id'      => $data['bidang_id'],
            'divisi_id'      => $data['divisi_id'],
            'cost_center_id' => $data['cost_center_id'],
            'tanggal'        => $data['tanggal'],
            'nilai'          => number_format((float) $data['nilai'], 2, '.', ''),
            'status'         => $data['status'],
            'keterangan'     => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            'created_by'     => auth_id() ?: null,
        ]);
    }

    public static function ubahStatusCommitment(int $id, string $status): void
    {
        if (!array_key_exists($status, self::COMMIT_STATUS)) {
            throw new \RuntimeException('Status komitmen tidak dikenal.');
        }
        $row = Db::one('SELECT nomor_po, status FROM budget_commitments WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Komitmen tidak ditemukan.');
        }
        Db::exec('UPDATE budget_commitments SET status = ? WHERE id = ?', [$status, $id]);

        Audit::log('update', 'budget', [
            'ref_table'   => 'budget_commitments',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor_po'],
            'description' => 'Ubah status komitmen ' . $row['nomor_po'] . ' menjadi ' . $status,
        ]);
    }

    public static function hapusCommitment(int $id): void
    {
        $row = Db::one('SELECT nomor_po, status FROM budget_commitments WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Komitmen tidak ditemukan.');
        }
        if ((string) $row['status'] === 'closed') {
            throw new \RuntimeException('Komitmen yang sudah selesai tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM budget_commitments WHERE id = ?', [$id]);
    }

    /**
     * Pemeriksaan batas anggaran untuk modul transaksi (dipakai bila `budget_hard_stop` aktif).
     *
     * @return array<string,mixed>|null null = masih dalam pagu
     */
    public static function cekBatasAnggaran(int $coaId, ?int $divisiId, int $tahun, float $tambahan): ?array
    {
        $budget = self::aktif($tahun);
        if ($budget === null) {
            return null;
        }

        $pagu = (float) Db::val(
            "SELECT COALESCE(SUM(total),0) FROM budget_lines
             WHERE budget_id = ? AND coa_id = ? AND COALESCE(divisi_id,0) = ? AND jenis = 'beban'",
            [(int) $budget['id'], $coaId, $divisiId ?? 0]
        );
        if ($pagu <= 0) {
            return null;
        }

        $realisasi = self::realisasiMap($tahun, 12)[$coaId . '|' . ($divisiId ?? 0)] ?? 0.0;
        if ($realisasi + $tambahan <= $pagu + 0.005) {
            return null;
        }

        return [
            'budget_id'  => (int) $budget['id'],
            'budget_nomor' => (string) $budget['nomor'],
            'coa_id'     => $coaId,
            'pagu'       => $pagu,
            'realisasi'  => $realisasi,
            'tambahan'   => $tambahan,
        ];
    }
}
