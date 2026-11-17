<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Db;
use Lju\Services\ClosingService;
use Lju\Services\JournalService;
use Lju\Services\LedgerService;
use Lju\Services\PeriodService;

/**
 * Tutup buku bulanan & tahunan, penguncian periode, dan sinkronisasi saldo.
 */
final class ClosingController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('period.view');

        $tahun = (int) input('tahun', (int) (periode_aktif()['tahun'] ?? date('Y')));
        $years = PeriodService::years();
        if ($years !== [] && !in_array($tahun, $years, true)) {
            $tahun = (int) $years[0];
        }

        $periods = PeriodService::forYear($tahun);

        $counts = [];
        foreach (Db::all(
            'SELECT periode_id, status, COUNT(*) AS jumlah
             FROM journal_entries WHERE fiscal_year = ? GROUP BY periode_id, status',
            [$tahun]
        ) as $row) {
            $counts[(int) $row['periode_id']][(string) $row['status']] = (int) $row['jumlah'];
        }

        $totals = [];
        foreach (Db::all(
            'SELECT periode_id, COALESCE(SUM(total_debit),0) AS debit, COALESCE(SUM(total_kredit),0) AS kredit
             FROM journal_entries WHERE fiscal_year = ? AND status = ? GROUP BY periode_id',
            [$tahun, 'posted']
        ) as $row) {
            $totals[(int) $row['periode_id']] = ['debit' => (float) $row['debit'], 'kredit' => (float) $row['kredit']];
        }

        $fiscal = PeriodService::fiscalYear($tahun);

        $neraca = LedgerService::neracaSaldo($tahun . '-01-01', $tahun . '-12-31');
        $labaRugi = [
            'pendapatan' => LedgerService::saldoTipe('pendapatan', $tahun . '-12-31'),
            'beban'      => LedgerService::saldoTipe('beban', $tahun . '-12-31'),
        ];
        $labaRugi['laba'] = -1 * ($labaRugi['pendapatan'] + $labaRugi['beban']);

        $saldoRows = (int) Db::val('SELECT COUNT(*) FROM account_period_balances b JOIN fiscal_periods p ON p.id = b.periode_id WHERE p.tahun = ?', [$tahun]);
        $jurnalPosted = (int) Db::val('SELECT COUNT(*) FROM journal_entries WHERE fiscal_year = ? AND status = ?', [$tahun, 'posted']);

        $this->page('pages/closing/index', [
            'pageTitle'    => 'Tutup Buku',
            'pageSubtitle' => 'Kontrol periode, tutup buku bulanan/tahunan, dan pemindahan saldo.',
            'breadcrumbs'  => [
                ['label' => 'Accounting', 'url' => null],
                ['label' => 'Tutup Buku', 'url' => null],
            ],
            'tahun'      => $tahun,
            'years'      => $years,
            'periods'    => $periods,
            'counts'     => $counts,
            'totals'     => $totals,
            'fiscal'     => $fiscal,
            'neraca'     => $neraca,
            'labaRugi'   => $labaRugi,
            'riwayat'    => ClosingService::riwayat(15),
            'saldoRows'  => $saldoRows,
            'jurnalPosted' => $jurnalPosted,
            'canManage'  => can('period.manage'),
        ]);
    }

    public function close(string $id): void
    {
        $this->requireCan('period.manage');
        $catatan = trim((string) input('catatan', ''));

        try {
            ClosingService::tutupBulanan((int) $id, $catatan);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('closing');
        }

        flash_success('Periode berhasil ditutup. Saldo akhir dipindahkan ke periode berikutnya.');
        redirect('closing');
    }

    public function lock(string $id): void
    {
        $this->requireCan('period.manage');

        try {
            ClosingService::kunci((int) $id);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('closing');
        }

        flash_success('Periode dikunci permanen.');
        redirect('closing');
    }

    public function reopen(string $id): void
    {
        $this->requireCan('period.manage');
        $alasan = trim((string) input('alasan', ''));

        try {
            ClosingService::bukaKembali((int) $id, $alasan);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('closing');
        }

        flash_success('Periode dibuka kembali. Perubahan tercatat pada audit trail.');
        redirect('closing');
    }

    public function closeYear(): void
    {
        $this->requireCan('period.manage');
        $tahun = (int) input('tahun', (int) date('Y'));
        $catatan = trim((string) input('catatan', ''));

        try {
            $journalId = ClosingService::tutupTahunan($tahun, $catatan);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('closing', ['tahun' => $tahun]);
        }

        $journal = $journalId > 0 ? JournalService::find($journalId) : null;
        flash_success($journal !== null
            ? 'Tutup buku tahunan ' . $tahun . ' selesai. Jurnal penutup ' . $journal['nomor'] . ' telah diposting.'
            : 'Tutup buku tahunan ' . $tahun . ' selesai (tidak ada saldo pendapatan/beban yang perlu ditutup).');
        redirect('closing', ['tahun' => $tahun]);
    }

    public function sync(): void
    {
        $this->requireCan('period.manage');
        $tahun = (int) input('tahun', 0);

        try {
            $jumlah = ClosingService::sinkronSaldo($tahun > 0 ? $tahun : null);
        } catch (\Throwable $e) {
            flash_error('Sinkronisasi saldo gagal: ' . $e->getMessage());
            redirect('closing');
        }

        flash_success('Saldo ' . $jumlah . ' tahun buku berhasil dihitung ulang dari jurnal posted.');
        redirect('closing', $tahun > 0 ? ['tahun' => $tahun] : []);
    }

}
