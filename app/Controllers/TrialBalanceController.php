<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Db;
use Lju\Services\LedgerService;
use Lju\Services\PeriodService;

/**
 * Neraca saldo: saldo awal, mutasi, saldo akhir, dan pemeriksaan keseimbangan.
 */
final class TrialBalanceController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('journal.view');

        $periodeId = (int) input('periode', 0);
        $periode = $periodeId > 0
            ? Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId])
            : periode_aktif();

        $periode ??= periode_aktif();

        $mode = (string) input('mode', 'periode');   // periode | kumulatif
        $tipe = (string) input('tipe', '');

        $to = (string) ($periode['tanggal_selesai'] ?? date('Y-m-t'));
        $from = $mode === 'kumulatif'
            ? (string) $periode['tahun'] . '-01-01'
            : (string) ($periode['tanggal_mulai'] ?? date('Y-m-01'));

        $hasil = LedgerService::neracaSaldo($from, $to, [], $tipe !== '' ? $tipe : null);
        $rekap = LedgerService::rekapTipe($hasil['rows']);

        $this->page('pages/trial-balance/index', [
            'pageTitle'    => 'Neraca Saldo',
            'pageSubtitle' => 'Saldo awal, mutasi, dan saldo akhir seluruh akun transaksi.',
            'breadcrumbs'  => [
                ['label' => 'Accounting', 'url' => null],
                ['label' => 'Neraca Saldo', 'url' => null],
            ],
            'rows'        => $hasil['rows'],
            'totalDebit'  => $hasil['total_debit'],
            'totalKredit' => $hasil['total_kredit'],
            'totalAwal'   => $hasil['total_awal'],
            'selisih'     => $hasil['selisih'],
            'seimbang'    => $hasil['seimbang'],
            'rekap'       => $rekap,
            'periode'     => $periode,
            'periodeId'   => (int) ($periode['id'] ?? 0),
            'periods'     => Db::all('SELECT id, kode, nama FROM fiscal_periods ORDER BY tanggal_mulai DESC LIMIT 36'),
            'mode'        => $mode,
            'tipe'        => $tipe,
            'from'        => $from,
            'to'          => $to,
            'tipeList'    => ['aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'ekuitas' => 'Ekuitas', 'pendapatan' => 'Pendapatan', 'beban' => 'Beban'],
            'years'       => PeriodService::years(),
        ]);
    }
}
