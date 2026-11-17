<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Services\PeriodService;

/**
 * Periode akuntansi: daftar bulan/tahun buku beserta statusnya.
 */
final class PeriodController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('period.view');

        $tahun = (int) input('tahun', (int) (periode_aktif()['tahun'] ?? date('Y')));
        PeriodService::ensureYear($tahun);

        $periods = PeriodService::forYear($tahun);
        $fiscalYear = PeriodService::fiscalYear($tahun);

        $journalCounts = [];
        if (Db::tableExists('journal_entries')) {
            foreach (Db::all(
                'SELECT periode_id, COUNT(*) AS jumlah FROM journal_entries GROUP BY periode_id'
            ) as $row) {
                $journalCounts[(int) $row['periode_id']] = (int) $row['jumlah'];
            }
        }

        $statusCount = ['open' => 0, 'closed' => 0, 'locked' => 0];
        foreach ($periods as $period) {
            $status = (string) $period['status'];
            $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
        }

        $this->page('pages/period/index', [
            'pageTitle'    => 'Periode Akuntansi',
            'pageSubtitle' => 'Pembukaan, penutupan, dan penguncian periode buku.',
            'breadcrumbs'  => [
                ['label' => 'Master Data', 'url' => null],
                ['label' => 'Periode Akuntansi', 'url' => null],
            ],
            'periods'      => $periods,
            'fiscalYear'   => $fiscalYear,
            'years'        => PeriodService::years(),
            'tahun'        => $tahun,
            'journalCounts' => $journalCounts,
            'statusCount'  => $statusCount,
            'current'      => periode_aktif(),
            'canManage'    => can('period.manage'),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $this->requireCan('period.manage');

        $periodId = (int) $id;
        $period = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodId]);
        if ($period === null) {
            flash_error('Periode tidak ditemukan.');
            back('master/period');
        }

        $status = (string) input('status', 'open');
        if (!in_array($status, ['open', 'closed', 'locked'], true)) {
            flash_error('Status periode tidak valid.');
            back('master/period');
        }

        Db::update('fiscal_periods', [
            'status'    => $status,
            'closed_by' => $status === 'open' ? null : auth_id(),
            'closed_at' => $status === 'open' ? null : now(),
        ], 'id = :id', [':id' => $periodId]);

        Audit::log('close', 'period', [
            'ref_table'   => 'fiscal_periods',
            'ref_id'      => $periodId,
            'ref_code'    => (string) $period['kode'],
            'description' => 'Status periode ' . $period['kode'] . ' diubah menjadi ' . $status,
            'old'         => ['status' => (string) $period['status']],
            'new'         => ['status' => $status],
        ]);

        flash_success('Status periode ' . $period['kode'] . ' diperbarui menjadi ' . $status . '.');
        back('master/period');
    }
}
