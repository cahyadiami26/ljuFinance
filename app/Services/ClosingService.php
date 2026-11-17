<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Proses tutup buku bulanan & tahunan.
 *
 * Bulanan  : mengunci periode setelah semua jurnal posted, memindahkan saldo
 *            akhir menjadi saldo awal periode berikutnya (account_period_balances).
 * Tahunan  : membuat jurnal penutup (menutup pendapatan & beban ke Laba Tahun
 *            Berjalan, lalu ke Saldo Laba), mengunci seluruh periode, dan
 *            menutup tahun buku.
 */
final class ClosingService
{
    /** Laba (rugi) satu periode = pendapatan - beban dari jurnal posted. */
    public static function labaRugiPeriode(int $periodeId): float
    {
        $row = Db::one(
            'SELECT
                COALESCE(SUM(CASE WHEN c.tipe = ? THEN jl.kredit - jl.debit ELSE 0 END), 0) AS pendapatan,
                COALESCE(SUM(CASE WHEN c.tipe = ? THEN jl.debit - jl.kredit ELSE 0 END), 0) AS beban
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.periode_id = ? AND je.status = ?',
            ['pendapatan', 'beban', $periodeId, 'posted']
        );

        return round((float) ($row['pendapatan'] ?? 0) - (float) ($row['beban'] ?? 0), 2);
    }

    /** @return array<int,array<string,mixed>> */
    public static function riwayat(int $limit = 20): array
    {
        return Db::all(
            'SELECT pc.*, p.kode AS periode_kode, p.nama AS periode_nama, j.nomor AS journal_nomor, u.name AS user_name
             FROM period_closings pc
             LEFT JOIN fiscal_periods p ON p.id = pc.periode_id
             LEFT JOIN journal_entries j ON j.id = pc.journal_id
             LEFT JOIN app_users u ON u.id = pc.executed_by
             ORDER BY pc.id DESC LIMIT ' . max(1, $limit)
        );
    }

    public static function tutupBulanan(int $periodeId, string $catatan = ''): void
    {
        $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        if ($periode === null) {
            throw new \RuntimeException('Periode tidak ditemukan.');
        }
        if ((string) $periode['status'] !== 'open') {
            throw new \RuntimeException('Periode ' . $periode['nama'] . ' sudah ' . $periode['status'] . '.');
        }

        $ringkasan = JournalService::ringkasanPeriode($periodeId);
        if ($ringkasan['belum_posting'] > 0) {
            throw new \RuntimeException(
                'Masih ada ' . $ringkasan['belum_posting'] . ' jurnal yang belum posted pada periode ini. ' .
                'Posting atau hapus terlebih dahulu sebelum tutup buku.'
            );
        }

        $labaRugi = self::labaRugiPeriode($periodeId);

        Db::transaction(static function () use ($periode, $periodeId, $catatan, $ringkasan, $labaRugi): void {
            Db::update('fiscal_periods', [
                'status'    => 'closed',
                'closed_by' => auth_id() ?: null,
                'closed_at' => now(),
                'catatan'   => $catatan !== '' ? mb_substr($catatan, 0, 255) : null,
            ], 'id = :id', [':id' => $periodeId]);

            Db::insert('period_closings', [
                'periode_id'   => $periodeId,
                'tipe'         => 'bulanan',
                'journal_id'   => null,
                'total_debit'  => number_format($ringkasan['total_debit'], 2, '.', ''),
                'total_kredit' => number_format($ringkasan['total_kredit'], 2, '.', ''),
                'laba_rugi'    => number_format($labaRugi, 2, '.', ''),
                'catatan'      => $catatan !== '' ? mb_substr($catatan, 0, 255) : null,
                'executed_by'  => auth_id() ?: null,
            ]);

            JournalService::rebuildYear((int) $periode['tahun']);
        });

        Audit::log('close', 'period', [
            'ref_table'   => 'fiscal_periods',
            'ref_id'      => $periodeId,
            'ref_code'    => (string) $periode['kode'],
            'description' => 'Tutup buku bulanan ' . $periode['nama'],
        ]);
    }

    public static function kunci(int $periodeId): void
    {
        $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        if ($periode === null) {
            throw new \RuntimeException('Periode tidak ditemukan.');
        }
        if ((string) $periode['status'] !== 'closed') {
            throw new \RuntimeException('Periode harus ditutup terlebih dahulu sebelum dikunci.');
        }

        Db::update('fiscal_periods', [
            'status'    => 'locked',
            'closed_by' => auth_id() ?: (int) $periode['closed_by'],
            'closed_at' => $periode['closed_at'] ?? now(),
        ], 'id = :id', [':id' => $periodeId]);

        Audit::log('close', 'period', [
            'ref_table'   => 'fiscal_periods',
            'ref_id'      => $periodeId,
            'ref_code'    => (string) $periode['kode'],
            'description' => 'Kunci periode ' . $periode['nama'],
        ]);
    }

    public static function bukaKembali(int $periodeId, string $alasan): void
    {
        $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        if ($periode === null) {
            throw new \RuntimeException('Periode tidak ditemukan.');
        }
        if ((string) $periode['status'] === 'locked') {
            throw new \RuntimeException('Periode terkunci permanen dan tidak dapat dibuka kembali.');
        }
        if ((string) $periode['status'] === 'open') {
            throw new \RuntimeException('Periode sudah terbuka.');
        }
        if (trim($alasan) === '') {
            throw new \RuntimeException('Alasan pembukaan kembali wajib diisi.');
        }

        Db::update('fiscal_periods', [
            'status'    => 'open',
            'closed_by' => null,
            'closed_at' => null,
            'catatan'   => mb_substr('Dibuka kembali: ' . $alasan, 0, 255),
        ], 'id = :id', [':id' => $periodeId]);

        Audit::log('update', 'period', [
            'ref_table'   => 'fiscal_periods',
            'ref_id'      => $periodeId,
            'ref_code'    => (string) $periode['kode'],
            'description' => 'Buka kembali periode ' . $periode['nama'] . ' — ' . $alasan,
        ]);
    }

    /**
     * Tutup buku tahunan: buat & posting jurnal penutup, kunci seluruh periode.
     */
    public static function tutupTahunan(int $tahun, string $catatan = ''): int
    {
        $fiscal = PeriodService::fiscalYear($tahun);
        if ($fiscal === null) {
            throw new \RuntimeException('Tahun buku ' . $tahun . ' belum dibuat.');
        }
        if ((string) $fiscal['status'] !== 'open') {
            throw new \RuntimeException('Tahun buku ' . $tahun . ' sudah ditutup.');
        }

        $belumPosting = (int) Db::val(
            'SELECT COUNT(*) FROM journal_entries WHERE fiscal_year = ? AND status IN (?, ?, ?)',
            [$tahun, 'draft', 'submitted', 'approved']
        );
        if ($belumPosting > 0) {
            throw new \RuntimeException('Masih ada ' . $belumPosting . ' jurnal belum posted pada tahun ' . $tahun . '.');
        }

        $awal = (string) $fiscal['tanggal_mulai'];
        $akhir = (string) $fiscal['tanggal_selesai'];

        $pendapatan = LedgerService::neracaSaldo($awal, $akhir, [], 'pendapatan')['rows'];
        $beban = LedgerService::neracaSaldo($awal, $akhir, [], 'beban')['rows'];

        $akunLaba = Db::one('SELECT id FROM coa_accounts WHERE kode = ?', ['3-2100']);
        $akunSaldoLaba = Db::one('SELECT id FROM coa_accounts WHERE kode = ?', ['3-2000']);
        if ($akunLaba === null || $akunSaldoLaba === null) {
            throw new \RuntimeException('Akun 3-2100 (Laba Tahun Berjalan) atau 3-2000 (Saldo Laba) tidak ditemukan pada COA.');
        }

        $lines = [];
        $totalPendapatan = 0.0;
        $totalBeban = 0.0;

        foreach ($pendapatan as $row) {
            $nilai = (float) $row['akhir'];
            if (abs($nilai) < 0.005) {
                continue;
            }
            $lines[] = [
                'coa_id'    => (int) $row['id'],
                'deskripsi' => 'Penutupan pendapatan ' . $row['kode'] . ' ' . $row['nama'],
                'debit'     => abs($nilai),
                'kredit'    => 0.0,
            ];
            $totalPendapatan += abs($nilai);
        }

        foreach ($beban as $row) {
            $nilai = (float) $row['akhir'];
            if (abs($nilai) < 0.005) {
                continue;
            }
            $lines[] = [
                'coa_id'    => (int) $row['id'],
                'deskripsi' => 'Penutupan beban ' . $row['kode'] . ' ' . $row['nama'],
                'debit'     => 0.0,
                'kredit'    => abs($nilai),
            ];
            $totalBeban += abs($nilai);
        }

        $laba = round($totalPendapatan - $totalBeban, 2);

        if ($laba > 0) {
            $lines[] = ['coa_id' => (int) $akunLaba['id'], 'deskripsi' => 'Laba tahun berjalan ' . $tahun, 'debit' => 0.0, 'kredit' => $laba];
            $lines[] = ['coa_id' => (int) $akunLaba['id'], 'deskripsi' => 'Pemindahan laba ke saldo laba', 'debit' => $laba, 'kredit' => 0.0];
            $lines[] = ['coa_id' => (int) $akunSaldoLaba['id'], 'deskripsi' => 'Penambahan saldo laba ' . $tahun, 'debit' => 0.0, 'kredit' => $laba];
        } elseif ($laba < 0) {
            $rugi = abs($laba);
            $lines[] = ['coa_id' => (int) $akunLaba['id'], 'deskripsi' => 'Rugi tahun berjalan ' . $tahun, 'debit' => $rugi, 'kredit' => 0.0];
            $lines[] = ['coa_id' => (int) $akunLaba['id'], 'deskripsi' => 'Pemindahan rugi ke saldo laba', 'debit' => 0.0, 'kredit' => $rugi];
            $lines[] = ['coa_id' => (int) $akunSaldoLaba['id'], 'deskripsi' => 'Pengurangan saldo laba ' . $tahun, 'debit' => $rugi, 'kredit' => 0.0];
        }

        $totalDebit = array_sum(array_column($lines, 'debit'));
        $totalKredit = array_sum(array_column($lines, 'kredit'));

        if (abs($totalDebit - $totalKredit) > 0.005) {
            throw new \RuntimeException('Jurnal penutup tidak seimbang (debit ' . rupiah($totalDebit) . ' / kredit ' . rupiah($totalKredit) . ').');
        }

        $periode = Db::one('SELECT * FROM fiscal_periods WHERE tahun = ? ORDER BY bulan DESC LIMIT 1', [$tahun]);

        // Tidak ada saldo pendapatan/beban: cukup kunci periode & tutup tahun buku.
        if ($lines === []) {
            Db::transaction(static function () use ($tahun, $catatan, $periode): void {
                Db::exec('UPDATE fiscal_periods SET status = ?, closed_by = ?, closed_at = ? WHERE tahun = ?', [
                    'locked', auth_id() ?: null, now(), $tahun,
                ]);
                Db::exec('UPDATE fiscal_years SET status = ?, closed_by = ?, closed_at = ?, catatan = ? WHERE tahun = ?', [
                    'closed', auth_id() ?: null, now(), $catatan !== '' ? mb_substr($catatan, 0, 255) : null, $tahun,
                ]);
                Db::insert('period_closings', [
                    'periode_id'  => (int) ($periode['id'] ?? 0),
                    'tipe'        => 'tahunan',
                    'journal_id'  => null,
                    'total_debit' => '0.00',
                    'total_kredit' => '0.00',
                    'laba_rugi'   => '0.00',
                    'catatan'     => $catatan !== '' ? mb_substr($catatan, 0, 255) : 'Tidak ada saldo pendapatan/beban',
                    'executed_by' => auth_id() ?: null,
                ]);
                JournalService::rebuildYear($tahun);
            });

            Audit::log('close', 'period', [
                'ref_table'   => 'fiscal_years',
                'ref_id'      => (int) $fiscal['id'],
                'ref_code'    => 'FY-' . $tahun,
                'description' => 'Tutup buku tahunan ' . $tahun . ' (tanpa jurnal penutup)',
            ]);

            return 0;
        }

        $journalId = Db::transaction(static function () use ($lines, $tahun, $akhir, $periode, $totalDebit, $totalKredit, $catatan): int {
            $periodeId = (int) ($periode['id'] ?? 0);
            if ($periodeId === 0) {
                throw new \RuntimeException('Periode Desember ' . $tahun . ' tidak ditemukan.');
            }

            $journalId = Db::insert('journal_entries', [
                'nomor'        => NumberingService::next('JU', $akhir),
                'tanggal'      => $akhir,
                'periode_id'   => $periodeId,
                'fiscal_year'  => $tahun,
                'tipe'         => 'penutup',
                'sumber'       => 'penutup',
                'referensi'    => 'CLOSE-' . $tahun,
                'deskripsi'    => 'Jurnal penutup tahun buku ' . $tahun,
                'total_debit'  => number_format($totalDebit, 2, '.', ''),
                'total_kredit' => number_format($totalKredit, 2, '.', ''),
                'status'       => 'posted',
                'created_by'   => auth_id() ?: null,
                'posted_by'    => auth_id() ?: null,
                'posted_at'    => now(),
            ]);

            foreach ($lines as $index => $line) {
                Db::insert('journal_lines', [
                    'journal_id' => $journalId,
                    'urutan'     => $index + 1,
                    'coa_id'     => (int) $line['coa_id'],
                    'deskripsi'  => $line['deskripsi'],
                    'debit'      => number_format((float) $line['debit'], 2, '.', ''),
                    'kredit'     => number_format((float) $line['kredit'], 2, '.', ''),
                ]);
            }

            Db::exec('UPDATE fiscal_periods SET status = ?, closed_by = ?, closed_at = ? WHERE tahun = ?', [
                'locked', auth_id() ?: null, now(), $tahun,
            ]);

            Db::exec('UPDATE fiscal_years SET status = ?, closed_by = ?, closed_at = ?, catatan = ? WHERE tahun = ?', [
                'closed', auth_id() ?: null, now(), $catatan !== '' ? mb_substr($catatan, 0, 255) : null, $tahun,
            ]);

            Db::insert('period_closings', [
                'periode_id'   => $periodeId,
                'tipe'         => 'tahunan',
                'journal_id'   => $journalId,
                'total_debit'  => number_format($totalDebit, 2, '.', ''),
                'total_kredit' => number_format($totalKredit, 2, '.', ''),
                'laba_rugi'    => number_format($totalDebit - $totalKredit, 2, '.', ''),
                'catatan'      => $catatan !== '' ? mb_substr($catatan, 0, 255) : null,
                'executed_by'  => auth_id() ?: null,
            ]);

            JournalService::rebuildYear($tahun);

            return $journalId;
        });

        Audit::log('close', 'period', [
            'ref_table'   => 'fiscal_years',
            'ref_id'      => (int) $fiscal['id'],
            'ref_code'    => 'FY-' . $tahun,
            'description' => 'Tutup buku tahunan ' . $tahun,
            'new'         => ['laba_rugi' => $laba, 'journal_id' => $journalId],
        ]);

        return $journalId;
    }

    /** Sinkronkan ulang cache saldo seluruh tahun yang tersedia. */
    public static function sinkronSaldo(?int $tahun = null): int
    {
        $years = $tahun !== null
            ? [$tahun]
            : array_map(static fn ($r) => (int) $r['tahun'], Db::all('SELECT DISTINCT tahun FROM fiscal_periods ORDER BY tahun'));

        foreach ($years as $year) {
            JournalService::rebuildYear($year);
        }

        return count($years);
    }
}
