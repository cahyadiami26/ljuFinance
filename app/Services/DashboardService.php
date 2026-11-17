<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Auth;
use Lju\Core\Db;

/**
 * Agregasi angka untuk Executive Dashboard.
 *
 * Semua query didefenisikan agar tetap aman ketika modul tertentu belum
 * diimport (tabel tidak ada) — nilai kembali default 0 / array kosong.
 */
final class DashboardService
{
    /** @return array<string,mixed> */
    public static function data(): array
    {
        $periode = PeriodService::current();
        $tahun = (int) ($periode['tahun'] ?? date('Y'));

        $banks = self::bankBalances();
        $likuiditas = array_sum(array_column($banks, 'saldo_berjalan'));

        $piutang = self::totalReceivable();
        $hutang = self::totalPayable();
        $aging = self::agingMatrix();

        $rkap = self::budgetMonitoring($tahun);

        $pendapatan = self::accountTotal('pendapatan', $tahun);
        $beban = self::accountTotal('beban', $tahun);
        $asetTetap = self::fixedAssetBookValue();

        $asetLancar = $likuiditas + $piutang;
        $totalAset = $asetLancar + $asetTetap;
        $totalKewajiban = $hutang;

        $anggaran = (float) ($rkap['pagu'] ?? 0);
        $realisasi = (float) ($rkap['realisasi'] ?? 0);
        $serapan = $anggaran > 0 ? ($realisasi / $anggaran) * 100 : 0.0;

        return [
            'periode'    => $periode,
            'tahun'      => $tahun,
            'likuiditas' => $likuiditas,
            'banks'      => $banks,
            'bank_aktif' => count(array_filter($banks, static fn ($b) => (int) $b['is_active'] === 1)),
            'piutang'    => $piutang,
            'hutang'     => $hutang,
            'aging'      => $aging,
            'rkap'       => $rkap,
            'serapan'    => $serapan,
            'ringkasan'  => [
                'aset_lancar'     => $asetLancar,
                'aset_tetap'      => $asetTetap,
                'total_aset'      => $totalAset,
                'total_kewajiban' => $totalKewajiban,
                'ekuitas'         => $totalAset - $totalKewajiban,
                'pendapatan'      => $pendapatan,
                'beban'           => $beban,
                'laba'            => $pendapatan - $beban,
            ],
            'rasio'      => self::ratio($likuiditas, $piutang, $hutang),
            'approvals'  => self::pendingApprovals(5),
            'approval_count' => self::pendingApprovalCount(),
            'audits'     => self::recentAudits(6),
            'notifications' => self::unreadNotifications(),
            'counts'     => self::masterCounts(),
            'synced_at'  => self::lastSync(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function bankBalances(int $limit = 8): array
    {
        if (!Db::tableExists('ms_bank_accounts')) {
            return [];
        }
        try {
            return Db::all(
                'SELECT id, kode, nama, tipe, nama_bank, nomor_rekening, mata_uang, saldo_awal, saldo_berjalan, saldo_minimum, is_active
                 FROM ms_bank_accounts ORDER BY (tipe = "petty_cash"), saldo_berjalan DESC LIMIT ' . (int) $limit
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private static function totalReceivable(): float
    {
        if (!Db::tableExists('ar_invoices')) {
            return 0.0;
        }
        try {
            return (float) Db::val(
                "SELECT COALESCE(SUM(sisa),0) FROM ar_invoices WHERE status NOT IN ('draft','void','paid') AND sisa > 0"
            );
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function totalPayable(): float
    {
        if (!Db::tableExists('ap_invoices')) {
            return 0.0;
        }
        try {
            return (float) Db::val(
                "SELECT COALESCE(SUM(sisa),0) FROM ap_invoices WHERE status NOT IN ('draft','void','paid') AND sisa > 0"
            );
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Matriks aging piutang vs hutang.
     *
     * @return array{buckets:array<int,array<string,mixed>>,ar_total:float,ap_total:float,ar_over_30:float,ap_due_14:float}
     */
    private static function agingMatrix(): array
    {
        $buckets = [
            ['label' => 'Lancar (0 - 30 Hari)', 'ar' => 0.0, 'ap' => 0.0, 'variant' => 'primary', 'note' => ''],
            ['label' => '31 - 60 Hari', 'ar' => 0.0, 'ap' => 0.0, 'variant' => 'info', 'note' => ''],
            ['label' => '61 - 90 Hari', 'ar' => 0.0, 'ap' => 0.0, 'variant' => 'warning', 'note' => ''],
            ['label' => '> 90 Hari (Macet/Restruktur)', 'ar' => 0.0, 'ap' => 0.0, 'variant' => 'bahaya', 'note' => ''],
        ];

        $tables = ['ar' => 'ar_invoices', 'ap' => 'ap_invoices'];
        foreach ($tables as $key => $table) {
            if (!Db::tableExists($table)) {
                continue;
            }
            try {
                $rows = Db::all(
                    "SELECT
                        SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) <= 30 THEN sisa ELSE 0 END) AS b0,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 31 AND 60 THEN sisa ELSE 0 END) AS b1,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 61 AND 90 THEN sisa ELSE 0 END) AS b2,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) > 90 THEN sisa ELSE 0 END) AS b3
                     FROM {$table}
                     WHERE sisa > 0 AND status NOT IN ('draft','void')"
                );
                $row = $rows[0] ?? [];
                for ($i = 0; $i < 4; $i++) {
                    $buckets[$i][$key] = (float) ($row['b' . $i] ?? 0);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $notes = [
            'Surplus arus kas',
            'Defisit temporal',
            'Follow-up penagihan aktif',
            'Cadangan CKPN terbentuk',
        ];
        foreach ($buckets as $i => &$bucket) {
            $diff = $bucket['ar'] - $bucket['ap'];
            $bucket['selisih'] = $diff;
            if ($i === 0) {
                $bucket['note'] = $diff >= 0
                    ? 'Surplus arus kas: +' . rupiah_short($diff)
                    : 'Defisit arus kas: -' . rupiah_short(abs($diff));
            } else {
                $bucket['note'] = $notes[$i] ?? '';
            }
        }
        unset($bucket);

        $arTotal = array_sum(array_column($buckets, 'ar'));
        $apTotal = array_sum(array_column($buckets, 'ap'));
        $arOver30 = $buckets[1]['ar'] + $buckets[2]['ar'] + $buckets[3]['ar'];

        $apDue14 = 0.0;
        if (Db::tableExists('ap_invoices')) {
            try {
                $apDue14 = (float) Db::val(
                    "SELECT COALESCE(SUM(sisa),0) FROM ap_invoices
                     WHERE sisa > 0 AND status NOT IN ('draft','void')
                       AND tanggal_jatuh_tempo <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
                );
            } catch (\Throwable) {
                $apDue14 = 0.0;
            }
        }

        return [
            'buckets'     => $buckets,
            'ar_total'    => $arTotal,
            'ap_total'    => $apTotal,
            'ar_over_30'  => $arOver30,
            'ap_due_14'   => $apDue14,
        ];
    }

    /**
     * Monitoring RKAP: pagu, realisasi, komitmen, dan rincian per divisi.
     *
     * @return array<string,mixed>
     */
    private static function budgetMonitoring(int $tahun): array
    {
        $result = [
            'pagu' => 0.0,
            'realisasi' => 0.0,
            'komitmen' => 0.0,
            'divisi' => [],
            'judul' => 'RKAP ' . $tahun,
        ];

        $paguPerDivisi = [];
        if (Db::tableExists('budget_lines') && Db::tableExists('budgets')) {
            try {
                $result['pagu'] = (float) Db::val(
                    "SELECT COALESCE(SUM(bl.total),0)
                     FROM budget_lines bl JOIN budgets b ON b.id = bl.budget_id
                     WHERE b.status = 'aktif' AND b.tahun = ? AND bl.jenis = 'beban'",
                    [$tahun]
                );
                $rows = Db::all(
                    "SELECT bl.divisi_id, SUM(bl.total) AS pagu
                     FROM budget_lines bl JOIN budgets b ON b.id = bl.budget_id
                     WHERE b.status = 'aktif' AND b.tahun = ? AND bl.jenis = 'beban'
                     GROUP BY bl.divisi_id",
                    [$tahun]
                );
                foreach ($rows as $row) {
                    $paguPerDivisi[(int) $row['divisi_id']] = (float) $row['pagu'];
                }
            } catch (\Throwable) {
                // biarkan nol
            }
        }

        $realisasiPerDivisi = [];
        if (Db::tableExists('journal_lines')) {
            try {
                $rows = Db::all(
                    "SELECT jl.divisi_id, SUM(jl.debit - jl.kredit) AS realisasi
                     FROM journal_lines jl
                     JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe = 'beban'
                     JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?
                     WHERE jl.divisi_id IS NOT NULL
                     GROUP BY jl.divisi_id",
                    [$tahun]
                );
                foreach ($rows as $row) {
                    $realisasiPerDivisi[(int) $row['divisi_id']] = (float) $row['realisasi'];
                }
            } catch (\Throwable) {
                // biarkan nol
            }
        }

        if (Db::tableExists('ms_divisi')) {
            try {
                $divisiRows = Db::all(
                    "SELECT d.id, d.kode, d.nama,
                            (SELECT k.kode FROM ms_cost_centers k WHERE k.divisi_id = d.id ORDER BY k.id LIMIT 1) AS cc_kode
                     FROM ms_divisi d WHERE d.is_active = 1 ORDER BY d.id"
                );
                foreach ($divisiRows as $row) {
                    $id = (int) $row['id'];
                    $pagu = $paguPerDivisi[$id] ?? 0.0;
                    $real = $realisasiPerDivisi[$id] ?? 0.0;
                    if ($pagu <= 0 && $real <= 0) {
                        continue;
                    }
                    $result['divisi'][] = [
                        'id'        => $id,
                        'kode'      => $row['kode'],
                        'nama'      => $row['nama'],
                        'cc_kode'   => $row['cc_kode'] ?? '-',
                        'pagu'      => $pagu,
                        'realisasi' => $real,
                        'persen'    => $pagu > 0 ? min(999, ($real / $pagu) * 100) : 0.0,
                    ];
                }
            } catch (\Throwable) {
                // biarkan kosong
            }
        }

        usort($result['divisi'], static fn ($a, $b) => $b['pagu'] <=> $a['pagu']);
        $result['realisasi'] = array_sum(array_column($result['divisi'], 'realisasi'));

        if (Db::tableExists('budget_commitments')) {
            try {
                $result['komitmen'] = (float) Db::val(
                    "SELECT COALESCE(SUM(nilai),0) FROM budget_commitments WHERE status IN ('open','sebagian') AND YEAR(tanggal) = ?",
                    [$tahun]
                );
            } catch (\Throwable) {
                $result['komitmen'] = 0.0;
            }
        }

        return $result;
    }

    private static function accountTotal(string $tipe, int $tahun): float
    {
        if (!Db::tableExists('journal_lines')) {
            return 0.0;
        }
        try {
            $expression = $tipe === 'beban' || $tipe === 'aset'
                ? 'SUM(jl.debit - jl.kredit)'
                : 'SUM(jl.kredit - jl.debit)';

            return (float) Db::val(
                "SELECT COALESCE({$expression},0)
                 FROM journal_lines jl
                 JOIN coa_accounts c ON c.id = jl.coa_id AND c.tipe = ?
                 JOIN journal_entries j ON j.id = jl.journal_id AND j.status = 'posted' AND j.fiscal_year = ?",
                [$tipe, $tahun]
            );
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function fixedAssetBookValue(): float
    {
        if (!Db::tableExists('fixed_assets')) {
            return 0.0;
        }
        try {
            return (float) Db::val("SELECT COALESCE(SUM(nilai_buku),0) FROM fixed_assets WHERE status = 'aktif'");
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** @return array<string,mixed> */
    private static function ratio(float $kas, float $piutang, float $hutang): array
    {
        $lancar = $kas + $piutang;
        $rasio = $hutang > 0 ? $lancar / $hutang : 0.0;

        $label = 'Sehat';
        $variant = 'sukses';
        if ($rasio > 0 && $rasio < 1) {
            $label = 'Perlu Perhatian';
            $variant = 'bahaya';
        } elseif ($rasio >= 1 && $rasio < 1.5) {
            $label = 'Cukup';
            $variant = 'warning';
        }

        return [
            'current' => $rasio,
            'label'   => $label,
            'variant' => $variant,
            'solvabilitas' => $hutang > 0 ? (($lancar / $hutang) > 2 ? 'Aman' : 'Cukup') : 'Aman',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function pendingApprovals(int $limit): array
    {
        if (!Db::tableExists('approval_requests')) {
            return [];
        }
        try {
            return Db::all(
                "SELECT ar.*, u.name AS pemohon, d.nama AS divisi
                 FROM approval_requests ar
                 LEFT JOIN app_users u ON u.id = ar.requested_by
                 LEFT JOIN ms_divisi d ON d.id = ar.divisi_id
                 WHERE ar.status = 'pending'
                 ORDER BY ar.deadline IS NULL, ar.deadline ASC, ar.amount DESC
                 LIMIT " . (int) $limit
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private static function pendingApprovalCount(): int
    {
        if (!Db::tableExists('approval_requests')) {
            return 0;
        }
        try {
            return (int) Db::val("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'");
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private static function recentAudits(int $limit): array
    {
        if (!Db::tableExists('audit_logs')) {
            return [];
        }
        try {
            return Db::all(
                'SELECT id, user_name, action, module, ref_code, description, created_at
                 FROM audit_logs ORDER BY id DESC LIMIT ' . (int) $limit
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private static function unreadNotifications(): int
    {
        if (!Db::tableExists('notifications')) {
            return 0;
        }
        try {
            return (int) Db::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [Auth::id()]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string,int> */
    private static function masterCounts(): array
    {
        $counts = ['coa' => 0, 'bidang' => 0, 'divisi' => 0, 'proyek' => 0, 'vendor' => 0, 'customer' => 0, 'users' => 0];
        $map = [
            'coa'      => ['coa_accounts', 'is_active = 1'],
            'bidang'   => ['ms_bidang', 'is_active = 1'],
            'divisi'   => ['ms_divisi', 'is_active = 1'],
            'proyek'   => ['ms_projects', "status = 'berjalan'"],
            'vendor'   => ['ms_partners', "tipe IN ('vendor','both') AND is_active = 1"],
            'customer' => ['ms_partners', "tipe IN ('customer','both') AND is_active = 1"],
            'users'    => ['app_users', 'is_active = 1'],
        ];

        foreach ($map as $key => [$table, $where]) {
            if (!Db::tableExists($table)) {
                continue;
            }
            try {
                $counts[$key] = (int) Db::val("SELECT COUNT(*) FROM {$table} WHERE {$where}");
            } catch (\Throwable) {
                $counts[$key] = 0;
            }
        }

        return $counts;
    }

    private static function lastSync(): ?string
    {
        try {
            $value = Db::val('SELECT MAX(synced_at) FROM app_users');
            return $value !== null ? (string) $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
