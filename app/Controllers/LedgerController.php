<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Db;
use Lju\Services\LedgerService;
use Lju\Services\PeriodService;

/**
 * Buku besar per akun + buku besar pembantu per dimensi.
 */
final class LedgerController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('journal.view');

        $coaId = (int) input('coa', 0);
        $periode = periode_aktif();
        $from = (string) input('from', (string) ($periode['tanggal_mulai'] ?? date('Y-m-01')));
        $to = (string) input('to', (string) ($periode['tanggal_selesai'] ?? date('Y-m-t')));

        if (strtotime($from) === false) {
            $from = date('Y-m-01');
        }
        if (strtotime($to) === false) {
            $to = date('Y-m-t');
        }

        $filter = [
            'bidang_id'      => (int) input('bidang', 0),
            'divisi_id'      => (int) input('divisi', 0),
            'project_id'     => (int) input('project', 0),
            'cost_center_id' => (int) input('cost_center', 0),
            'partner_id'     => (int) input('partner', 0),
        ];

        $akun = null;
        $running = 0.0;
        $saldoAwal = 0.0;
        $rows = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;
        $saldoAkhir = 0.0;
        $rekapBidang = [];
        $rekapDivisi = [];
        $rekapProyek = [];

        if ($coaId > 0) {
            $akun = Db::one('SELECT * FROM coa_accounts WHERE id = ?', [$coaId]);
            if ($akun !== null) {
                $saldoAwal = LedgerService::saldoAwal($coaId, $from, $filter);
                $rows = LedgerService::mutasi($coaId, $from, $to, $filter);

                $running = $saldoAwal;
                foreach ($rows as $index => $row) {
                    $running += (float) $row['debit'] - (float) $row['kredit'];
                    $rows[$index]['saldo'] = $running;
                    $totalDebit += (float) $row['debit'];
                    $totalKredit += (float) $row['kredit'];
                }
                $saldoAkhir = $running;

                $rekapBidang = LedgerService::rekapDimensi($rows, 'bidang_nama');
                $rekapDivisi = LedgerService::rekapDimensi($rows, 'divisi_nama');
                $rekapProyek = LedgerService::rekapDimensi($rows, 'project_nama');
            }
        }

        $this->page('pages/ledger/index', [
            'pageTitle'    => 'Buku Besar',
            'pageSubtitle' => 'Mutasi per akun beserta saldo berjalan dan rekap per dimensi.',
            'breadcrumbs'  => [
                ['label' => 'Accounting', 'url' => null],
                ['label' => 'Buku Besar', 'url' => null],
            ],
            'akunPerTipe' => LedgerService::akunPerTipe(),
            'akun'        => $akun,
            'coaId'       => $coaId,
            'from'        => $from,
            'to'          => $to,
            'filter'      => $filter,
            'rows'        => $rows,
            'saldoAwal'   => $saldoAwal,
            'saldoAkhir'  => $saldoAkhir,
            'totalDebit'  => $totalDebit,
            'totalKredit' => $totalKredit,
            'rekapBidang' => $rekapBidang,
            'rekapDivisi' => $rekapDivisi,
            'rekapProyek' => $rekapProyek,
            'bidangList'  => Db::all('SELECT id, nama FROM ms_bidang ORDER BY nama'),
            'divisiList'  => Db::all('SELECT id, nama FROM ms_divisi ORDER BY nama'),
            'projectList' => Db::all('SELECT id, nama FROM ms_projects ORDER BY nama'),
            'ccList'      => Db::all('SELECT id, nama FROM ms_cost_centers ORDER BY nama'),
            'partnerList' => Db::all('SELECT id, nama FROM ms_partners ORDER BY nama'),
            'periode'     => $periode,
            'years'       => PeriodService::years(),
        ]);
    }
}
