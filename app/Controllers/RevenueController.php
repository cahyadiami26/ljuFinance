<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\RevenueService;

/**
 * Pengakuan pendapatan kontrak (persentase penyelesaian / termin / selesai).
 */
final class RevenueController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('ar.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $contractId = (int) input('contract', 0);
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(r.nomor LIKE ? OR k.nomor LIKE ? OR k.judul LIKE ? OR pr.nama LIKE ?)';
            $params = array_merge($params, ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
        }
        if ($status !== '' && array_key_exists($status, RevenueService::STATUS)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($contractId > 0) {
            $where[] = 'r.contract_id = ?';
            $params[] = $contractId;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val(
            'SELECT COUNT(*) FROM revenue_recognitions r
             LEFT JOIN ms_contracts k ON k.id = r.contract_id
             LEFT JOIN ms_projects pr ON pr.id = r.project_id
             WHERE ' . $whereSql,
            $params
        );
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT r.*, pr.nama AS project_nama, k.nomor AS contract_nomor, k.judul AS contract_judul,
                    k.nilai_kontrak, p.nama AS partner_nama, pe.nama AS periode_nama, j.nomor AS journal_nomor
             FROM revenue_recognitions r
             LEFT JOIN ms_projects pr ON pr.id = r.project_id
             LEFT JOIN ms_contracts k ON k.id = r.contract_id
             LEFT JOIN ms_partners p ON p.id = k.partner_id
             LEFT JOIN fiscal_periods pe ON pe.id = r.periode_id
             LEFT JOIN journal_entries j ON j.id = r.journal_id
             WHERE ' . $whereSql . '
             ORDER BY r.tanggal DESC, r.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $tahun = (int) (periode_aktif()['tahun'] ?? date('Y'));

        $this->page('pages/revenue/index', [
            'pageTitle'    => 'Pengakuan Pendapatan',
            'pageSubtitle' => 'Akui pendapatan kontrak berdasarkan penyelesaian, termin, atau kontrak selesai.',
            'breadcrumbs'  => [
                ['label' => 'Hutang & Piutang', 'url' => null],
                ['label' => 'Pengakuan Pendapatan', 'url' => null],
            ],
            'pageActions'  => can('ar.manage')
                ? '<a class="btn btn--primary" href="' . e(url('revenue/create')) . '">' . icon('plus', 15) . ' Pengakuan Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'contractId'   => $contractId,
            'contracts'    => $this->opsiContract(),
            'ringkasan'    => RevenueService::ringkasan($tahun),
            'metode'       => RevenueService::METODE,
            'tahun'        => $tahun,
        ]);
    }

    public function create(): void
    {
        $this->requireCan('ar.manage');

        $this->renderForm(null, [
            'partner_id' => null,
            'project_id' => null,
            'contract_id' => null,
            'tanggal'    => today(),
            'persentase' => 0,
            'nilai'      => 0,
            'metode'     => 'persentase_penyelesaian',
            'keterangan' => '',
        ]);
    }

    public function store(): void
    {
        $this->requireCan('ar.manage');

        $data = RevenueService::normalisasi($_POST);
        $errors = RevenueService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = RevenueService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = RevenueService::find($id);
        Audit::log('create', 'revenue', [
            'ref_table'   => 'revenue_recognitions',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat pengakuan pendapatan ' . ($row['nomor'] ?? ''),
            'new'         => ['nilai' => $data['nilai'], 'metode' => $data['metode']],
        ]);

        flash_success('Pengakuan pendapatan ' . ($row['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('revenue/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('ar.view');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }

        $timeline = [];
        if (!empty($row['created_at'])) {
            $timeline[] = ['label' => 'Dibuat', 'at' => $row['created_at'], 'by' => $row['created_by_name'] ?? null, 'icon' => 'edit', 'variant' => 'primary'];
        }
        if (in_array((string) $row['status'], ['approved', 'posted'], true)) {
            $timeline[] = ['label' => 'Disetujui', 'at' => $row['updated_at'] ?? null, 'by' => null, 'icon' => 'check', 'variant' => 'sukses'];
        }
        if (!empty($row['journal_id'])) {
            $timeline[] = ['label' => 'Diposting', 'at' => $row['updated_at'] ?? null, 'by' => null, 'icon' => 'book', 'variant' => 'info'];
        }

        $this->page('pages/revenue/show', [
            'pageTitle'    => 'Pengakuan ' . $row['nomor'],
            'pageSubtitle' => (string) ($row['contract_judul'] ?? $row['project_nama'] ?? ''),
            'breadcrumbs'  => [
                ['label' => 'Pengakuan Pendapatan', 'url' => url('revenue')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('revenue')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'timeline'     => $timeline,
            'metode'       => RevenueService::METODE,
            'terakui'      => $row['contract_id'] ? RevenueService::terakuiKontrak((int) $row['contract_id']) : 0.0,
            'canEdit'      => can('ar.manage') && (string) $row['status'] === 'draft',
            'canSubmit'    => can('ar.manage') && (string) $row['status'] === 'draft',
            'canApprove'   => can('ar.approve') && (string) $row['status'] === 'submitted',
            'canPost'      => can('ar.manage') && (string) $row['status'] === 'approved',
            'canDelete'    => can('ar.manage') && (string) $row['status'] === 'draft',
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Dokumen berstatus ' . RevenueService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('revenue/' . (int) $id);
        }

        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Dokumen berstatus ' . RevenueService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('revenue/' . (int) $id);
        }

        $data = RevenueService::normalisasi($_POST);
        $errors = RevenueService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            RevenueService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'revenue', [
            'ref_table'   => 'revenue_recognitions',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah pengakuan pendapatan ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Pengakuan ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('revenue/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }

        try {
            RevenueService::ajukan((int) $id);
            flash_success('Pengakuan ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('revenue/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('ar.approve');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }

        try {
            RevenueService::setujui((int) $id);
            flash_success('Pengakuan ' . $row['nomor'] . ' disetujui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('revenue/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('ar.approve');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }

        try {
            RevenueService::tolak((int) $id, trim((string) input('alasan', '')));
            flash_success('Pengakuan ' . $row['nomor'] . ' dikembalikan ke draft.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('revenue/' . (int) $id);
    }

    public function post(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }

        try {
            $journalId = RevenueService::posting((int) $id);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Pengakuan pendapatan diposting ke jurnal ' . ($journal['nomor'] ?? '') . '.');
        } catch (\Throwable $e) {
            flash_error('Gagal posting: ' . $e->getMessage());
        }
        redirect('revenue/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = RevenueService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pengakuan pendapatan tidak ditemukan.');
        }

        try {
            RevenueService::hapus((int) $id);
            Audit::log('delete', 'revenue', [
                'ref_table'   => 'revenue_recognitions',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus pengakuan pendapatan ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('Pengakuan ' . $row['nomor'] . ' dihapus.');
            redirect('revenue');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('revenue/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/revenue/form', [
            'pageTitle'    => $id === null ? 'Pengakuan Pendapatan Baru' : 'Ubah Pengakuan Pendapatan',
            'pageSubtitle' => 'Pilih kontrak, metode pengakuan, dan nilai pendapatan yang diakui.',
            'breadcrumbs'  => [
                ['label' => 'Pengakuan Pendapatan', 'url' => url('revenue')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('revenue') : url('revenue/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'projects'     => $this->opsiProject(),
            'contracts'    => $this->opsiContract(),
            'metode'       => RevenueService::METODE,
        ]);
    }
}
