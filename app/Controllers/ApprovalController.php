<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Services\ApprovalService;

/**
 * Approval Center: antrean persetujuan lintas modul, aksi, dan delegasi.
 */
final class ApprovalController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('approval.view');

        $filters = [
            'status'  => (string) input('status', 'pending'),
            'module'  => (string) input('module', ''),
            'q'       => trim((string) input('q', '')),
            'overdue' => (string) input('overdue', '') === '1',
        ];

        $this->page('pages/approval/index', [
            'pageTitle'    => 'Approval Center',
            'pageSubtitle' => 'Antrean persetujuan seluruh modul dengan SLA dan riwayat keputusan.',
            'breadcrumbs'  => [
                ['label' => 'Approval Center', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('approval/delegations')) . '">' . icon('users', 15) . ' Delegasi</a>',
            'rows'         => ApprovalService::antrian($filters),
            'statistik'    => ApprovalService::statistik(),
            'statusList'   => ApprovalService::STATUS,
            'moduleList'   => ApprovalService::MODULES,
            'filters'      => $filters,
            'canAct'       => can('approval.act'),
        ]);
    }

    public function show(string $id): void
    {
        $this->requireCan('approval.view');

        $row = ApprovalService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Permintaan persetujuan tidak ditemukan.');
        }

        $this->page('pages/approval/show', [
            'pageTitle'    => 'Persetujuan ' . $row['nomor_dokumen'],
            'pageSubtitle' => (string) (ApprovalService::MODULES[(string) $row['module']] ?? $row['module']),
            'breadcrumbs'  => [
                ['label' => 'Approval Center', 'url' => url('approval')],
                ['label' => (string) $row['nomor_dokumen'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('approval')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'actions'      => ApprovalService::actions((int) $id),
            'statusList'   => ApprovalService::STATUS,
            'moduleList'   => ApprovalService::MODULES,
            'users'        => $this->opsiUser(),
            'canAct'       => can('approval.act') && (string) $row['status'] === 'pending',
        ]);
    }

    public function act(string $id): void
    {
        $this->requireCan('approval.act');

        $row = ApprovalService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Permintaan persetujuan tidak ditemukan.');
        }

        $aksi = (string) input('aksi', 'approve');

        try {
            $hasil = ApprovalService::putuskan((int) $id, $aksi, [
                'catatan'     => (string) input('catatan', ''),
                'delegasi_ke' => (int) input('delegasi_ke', 0) ?: null,
            ]);
            flash_success($hasil['pesan']);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('approval/' . (int) $id);
    }

    public function cancel(string $id): void
    {
        $this->requireCan('approval.act');

        try {
            ApprovalService::batalkan((int) $id);
            flash_success('Permintaan persetujuan dibatalkan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('approval/' . (int) $id);
    }

    public function delegations(): void
    {
        $this->requireCan('approval.view');

        $this->page('pages/approval/delegations', [
            'pageTitle'    => 'Delegasi Persetujuan',
            'pageSubtitle' => 'Pelimpahan kewenangan sementara antar pejabat, per modul atau seluruh modul.',
            'breadcrumbs'  => [
                ['label' => 'Approval Center', 'url' => url('approval')],
                ['label' => 'Delegasi', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('approval')) . '">' . icon('check-circle', 15) . ' Antrean Persetujuan</a>',
            'rows'         => ApprovalService::delegations(),
            'users'        => $this->opsiUser(),
            'moduleList'   => ApprovalService::MODULES,
            'canManage'    => can('approval.act'),
        ]);
    }

    public function delegationStore(): void
    {
        $this->requireCan('approval.act');

        $errors = ApprovalService::simpanDelegasi($_POST);
        if ($errors !== []) {
            error_bag($errors);
            flash_error(array_values($errors)[0]);
            redirect('approval/delegations');
        }

        flash_success('Delegasi persetujuan berhasil dicatat.');
        redirect('approval/delegations');
    }

    public function delegationClose(string $id): void
    {
        $this->requireCan('approval.act');

        try {
            ApprovalService::tutupDelegasi((int) $id);
            flash_success('Delegasi dinonaktifkan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('approval/delegations');
    }
}
