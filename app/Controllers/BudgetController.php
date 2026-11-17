<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Services\ApprovalService;
use Lju\Services\BudgetService;

/**
 * RKAP & pengendalian anggaran: penyusunan, revisi, aktivasi,
 * monitoring realisasi, dan komitmen pengadaan.
 */
final class BudgetController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('budget.view');

        $tahun = (int) input('tahun', date('Y'));
        $status = (string) input('status', '');
        $q = trim((string) input('q', ''));

        $where = ['b.tahun = ?'];
        $params = [$tahun];

        if ($status !== '' && array_key_exists($status, BudgetService::STATUS)) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(b.nomor LIKE ? OR b.judul LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%');
        }

        $rows = Db::all(
            'SELECT b.*, u.name AS created_by_name, p.nomor AS parent_nomor,
                    (SELECT COUNT(*) FROM budget_lines bl WHERE bl.budget_id = b.id) AS jumlah_baris
             FROM budgets b
             LEFT JOIN app_users u ON u.id = b.created_by
             LEFT JOIN budgets p ON p.id = b.parent_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY b.versi DESC, b.id DESC',
            $params
        );

        $this->page('pages/budget/index', [
            'pageTitle'    => 'RKAP & Anggaran',
            'pageSubtitle' => 'Rencana kerja & anggaran perusahaan, versi revisi, dan aktivasi pagu.',
            'breadcrumbs'  => [
                ['label' => 'Anggaran', 'url' => null],
                ['label' => 'RKAP', 'url' => null],
            ],
            'pageActions'  => can('budget.manage')
                ? '<a class="btn btn--primary" href="' . e(url('budget/create')) . '">' . icon('plus', 15) . ' RKAP Baru</a>'
                : '',
            'rows'        => $rows,
            'tahun'       => $tahun,
            'status'      => $status,
            'q'           => $q,
            'ringkasan'   => BudgetService::ringkasan($tahun),
            'commitment'  => BudgetService::commitmentRingkasan($tahun),
            'tahunList'   => $this->opsiTahunAnggaran(),
            'canManage'   => can('budget.manage'),
        ]);
    }

    /** @return array<int,int> */
    private function opsiTahunAnggaran(): array
    {
        $tahun = [];
        foreach (Db::all('SELECT DISTINCT tahun FROM budgets ORDER BY tahun DESC') as $row) {
            $tahun[] = (int) $row['tahun'];
        }
        $sekarang = (int) date('Y');
        if (!in_array($sekarang, $tahun, true)) {
            $tahun[] = $sekarang;
        }
        rsort($tahun);
        return $tahun;
    }

    public function create(): void
    {
        $this->requireCan('budget.manage');

        $this->renderForm(null, [
            'tahun'   => (int) date('Y'),
            'judul'   => 'RKAP ' . date('Y') . ' PT. Lampung Jasa Utama',
            'catatan' => '',
            'lines'   => [
                ['coa_id' => 0, 'jenis' => 'beban', 'bidang_id' => null, 'divisi_id' => null,
                 'business_unit_id' => null, 'cost_center_id' => null, 'project_id' => null,
                 'total' => 0, 'catatan' => ''],
            ],
        ]);
    }

    public function store(): void
    {
        $this->requireCan('budget.manage');

        $data = BudgetService::normalisasi($_POST);
        $errors = BudgetService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = BudgetService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = BudgetService::find($id);
        Audit::log('create', 'budget', [
            'ref_table'   => 'budgets',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat RKAP ' . ($row['nomor'] ?? ''),
            'new'         => ['pendapatan' => $data['tahun'], 'beban' => count($data['lines'])],
        ]);

        flash_success('RKAP ' . ($row['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('budget/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('budget.view');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        $sampaiBulan = (int) input('bulan', (int) date('n'));
        $sampaiBulan = max(1, min(12, $sampaiBulan));
        $monitoring = BudgetService::monitoring((int) $id, $sampaiBulan);

        $this->page('pages/budget/show', [
            'pageTitle'    => 'RKAP ' . $row['nomor'],
            'pageSubtitle' => (string) $row['judul'],
            'breadcrumbs'  => [
                ['label' => 'RKAP & Anggaran', 'url' => url('budget')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('budget')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'lines'        => BudgetService::lines((int) $id),
            'monitoring'   => $monitoring,
            'commitments'  => BudgetService::commitments((int) $id),
            'sampaiBulan'  => $sampaiBulan,
            'bulanList'    => BudgetService::BULAN,
            'jenis'        => BudgetService::JENIS,
            'canEdit'      => can('budget.manage') && in_array((string) $row['status'], ['draft', 'rejected'], true),
            'canSubmit'    => can('budget.manage') && in_array((string) $row['status'], ['draft', 'rejected'], true),
            'canApprove'   => can('budget.approve') && (string) $row['status'] === 'submitted',
            'canActivate'  => can('budget.approve') && (string) $row['status'] === 'approved',
            'canRevise'    => can('budget.manage') && in_array((string) $row['status'], ['aktif', 'approved', 'arsip'], true),
            'canArchive'   => can('budget.approve') && (string) $row['status'] === 'aktif',
            'canDelete'    => can('budget.manage') && in_array((string) $row['status'], ['draft', 'rejected'], true),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('budget.manage');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('RKAP berstatus ' . BudgetService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('budget/' . (int) $id);
        }

        $row['lines'] = BudgetService::lines((int) $id);
        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('budget.manage');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('RKAP berstatus ' . BudgetService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('budget/' . (int) $id);
        }

        $data = BudgetService::normalisasi($_POST);
        $errors = BudgetService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            BudgetService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'budget', [
            'ref_table'   => 'budgets',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah RKAP ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('RKAP ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('budget/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('budget.manage');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            BudgetService::ajukan((int) $id);
            ApprovalService::daftarkan('budget', (int) $id, [
                'nomor'  => (string) $row['nomor'],
                'judul'  => (string) $row['judul'],
                'amount' => (float) $row['total_beban'],
            ]);
            flash_success('RKAP ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('budget.approve');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            BudgetService::setujui((int) $id);
            ApprovalService::selesaikanOlehModul('budget', (int) $id, 'approved');
            flash_success('RKAP ' . $row['nomor'] . ' disetujui. Aktifkan untuk menjadi pagu tahun berjalan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('budget.approve');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            BudgetService::tolak((int) $id, trim((string) input('alasan', '')));
            ApprovalService::selesaikanOlehModul('budget', (int) $id, 'rejected');
            flash_success('RKAP ' . $row['nomor'] . ' ditolak.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/' . (int) $id);
    }

    public function activate(string $id): void
    {
        $this->requireCan('budget.approve');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            BudgetService::aktifkan((int) $id);
            flash_success('RKAP ' . $row['nomor'] . ' kini menjadi pagu aktif tahun ' . (int) $row['tahun'] . '.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/' . (int) $id);
    }

    public function archive(string $id): void
    {
        $this->requireCan('budget.approve');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            BudgetService::arsipkan((int) $id);
            flash_success('RKAP ' . $row['nomor'] . ' diarsipkan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/' . (int) $id);
    }

    public function revise(string $id): void
    {
        $this->requireCan('budget.manage');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            $baru = BudgetService::revisi((int) $id);
            $revisi = BudgetService::find($baru);
            flash_success('Revisi ' . ($revisi['nomor'] ?? '') . ' dibuat sebagai draft.');
            redirect('budget/' . $baru . '/edit');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('budget/' . (int) $id);
        }
    }

    public function destroy(string $id): void
    {
        $this->requireCan('budget.manage');

        $row = BudgetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'RKAP tidak ditemukan.');
        }

        try {
            BudgetService::hapus((int) $id);
            Audit::log('delete', 'budget', [
                'ref_table'   => 'budgets',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus RKAP ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('RKAP ' . $row['nomor'] . ' dihapus.');
            redirect('budget');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('budget/' . (int) $id);
        }
    }

    public function monitoring(): void
    {
        $this->requireCan('budget.view');

        $tahun = (int) input('tahun', date('Y'));
        $sampaiBulan = max(1, min(12, (int) input('bulan', (int) date('n'))));

        $budget = BudgetService::aktif($tahun);
        $monitoring = $budget !== null
            ? BudgetService::monitoring((int) $budget['id'], $sampaiBulan)
            : ['rows' => [], 'totals' => []];

        $this->page('pages/budget/monitoring', [
            'pageTitle'    => 'Monitoring Anggaran',
            'pageSubtitle' => 'Pagu, realisasi, komitmen, dan serapan anggaran per akun & divisi.',
            'breadcrumbs'  => [
                ['label' => 'Anggaran', 'url' => null],
                ['label' => 'Monitoring Realisasi', 'url' => null],
            ],
            'tahun'        => $tahun,
            'sampaiBulan'  => $sampaiBulan,
            'budget'       => $budget,
            'monitoring'   => $monitoring,
            'divisi'       => BudgetService::monitoringDivisi($tahun, $sampaiBulan),
            'costCenter'   => BudgetService::analisisCostCenter($tahun, $sampaiBulan),
            'profitCenter' => BudgetService::analisisProfitCenter($tahun, $sampaiBulan),
            'bulanList'    => BudgetService::BULAN,
            'tahunList'    => $this->opsiTahunAnggaran(),
            'alert'        => (float) setting('budget_alert_percent', 80),
            'hardStop'     => (string) setting('budget_hard_stop', '0') === '1',
        ]);
    }

    public function commitments(): void
    {
        $this->requireCan('budget.view');

        $status = (string) input('status', '');
        $q = trim((string) input('q', ''));
        $tahun = (int) input('tahun', date('Y'));

        $this->page('pages/budget/commitments', [
            'pageTitle'    => 'Komitmen Pengadaan',
            'pageSubtitle' => 'PO/kontrak yang sudah mengikat pagu anggaran sebelum realisasi jurnal.',
            'breadcrumbs'  => [
                ['label' => 'Anggaran', 'url' => null],
                ['label' => 'Komitmen PO', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('budget/monitoring')) . '">' . icon('chart', 15) . ' Monitoring</a>',
            'rows'         => BudgetService::commitments(0, $status, $q, $tahun),
            'status'       => $status,
            'q'            => $q,
            'tahun'        => $tahun,
            'tahunList'    => $this->opsiTahunAnggaran(),
            'statusList'   => BudgetService::COMMIT_STATUS,
            'ringkasan'    => BudgetService::commitmentRingkasan($tahun),
            'budgets'      => Db::all('SELECT id, nomor, judul, tahun, status FROM budgets ORDER BY tahun DESC, versi DESC'),
            'partners'     => $this->opsiPartnerTipe('vendor'),
            'coaBeban'     => $this->opsiCoaBeban(),
            'projects'     => $this->opsiProject(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'costCenters'  => $this->opsiCostCenter(),
            'canManage'    => can('budget.manage'),
        ]);
    }

    public function commitmentStore(): void
    {
        $this->requireCan('budget.manage');

        $hasil = BudgetService::normalisasiCommitment($_POST);
        $data = $hasil['q'];
        $errors = $hasil['errors'];

        if ($errors !== []) {
            error_bag($errors);
            flash_error(array_values($errors)[0]);
            redirect('budget/commitments');
        }

        try {
            $id = BudgetService::simpanCommitment($data);
            Audit::log('create', 'budget', [
                'ref_table'   => 'budget_commitments',
                'ref_id'      => $id,
                'ref_code'    => $data['nomor_po'],
                'description' => 'Catat komitmen pengadaan ' . $data['nomor_po'],
            ]);
            flash_success('Komitmen ' . $data['nomor_po'] . ' berhasil dicatat.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/commitments');
    }

    public function commitmentStatus(string $id): void
    {
        $this->requireCan('budget.manage');

        try {
            BudgetService::ubahStatusCommitment((int) $id, (string) input('status', 'open'));
            flash_success('Status komitmen diperbarui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/commitments');
    }

    public function commitmentDestroy(string $id): void
    {
        $this->requireCan('budget.manage');

        try {
            BudgetService::hapusCommitment((int) $id);
            flash_success('Komitmen dihapus.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('budget/commitments');
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/budget/form', [
            'pageTitle'    => $id === null ? 'RKAP Baru' : 'Ubah RKAP',
            'pageSubtitle' => 'Susun pagu pendapatan dan beban per akun serta dimensi organisasi.',
            'breadcrumbs'  => [
                ['label' => 'RKAP & Anggaran', 'url' => url('budget')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('budget') : url('budget/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'coaBeban'     => $this->opsiCoaBeban(),
            'coaPendapatan' => $this->opsiCoaPendapatan(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'businessUnits' => $this->opsiBusinessUnit(),
            'costCenters'  => $this->opsiCostCenter(),
            'projects'     => $this->opsiProject(),
            'jenis'        => BudgetService::JENIS,
            'bulanList'    => BudgetService::BULAN,
            'tahunList'    => $this->opsiTahunAnggaran(),
        ]);
    }
}
