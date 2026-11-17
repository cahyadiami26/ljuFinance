<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\ExpenseService;

/**
 * Pengajuan biaya operasional beserta PPN masukan dan potongan PPh.
 */
final class ExpenseController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('expense.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $tipe = (string) input('tipe', '');
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(e.nomor LIKE ? OR e.judul LIKE ? OR e.keterangan LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($status !== '' && array_key_exists($status, ExpenseService::STATUS)) {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        if ($tipe !== '' && array_key_exists($tipe, ExpenseService::TIPE)) {
            $where[] = 'e.tipe = ?';
            $params[] = $tipe;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM expense_requests e WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT e.*, u.name AS pemohon_nama, b.nama AS bank_nama, bd.nama AS bidang_nama,
                    p.nama AS periode_nama, j.nomor AS journal_nomor,
                    (SELECT COUNT(*) FROM expense_items i WHERE i.expense_id = e.id) AS jumlah_item
             FROM expense_requests e
             LEFT JOIN app_users u ON u.id = e.pemohon_user_id
             LEFT JOIN ms_bank_accounts b ON b.id = e.bank_account_id
             LEFT JOIN ms_bidang bd ON bd.id = e.bidang_id
             LEFT JOIN fiscal_periods p ON p.id = e.periode_id
             LEFT JOIN journal_entries j ON j.id = e.journal_id
             WHERE ' . $whereSql . '
             ORDER BY e.tanggal DESC, e.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $tahun = (int) (periode_aktif()['tahun'] ?? date('Y'));

        $this->page('pages/expense/index', [
            'pageTitle'    => 'Pengajuan Biaya',
            'pageSubtitle' => 'Biaya operasional dengan perhitungan PPN masukan dan potongan PPh.',
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => null],
                ['label' => 'Pengajuan Biaya', 'url' => null],
            ],
            'pageActions'  => can('expense.manage')
                ? '<a class="btn btn--primary" href="' . e(url('expense/create')) . '">' . icon('plus', 15) . ' Pengajuan Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'tipe'         => $tipe,
            'ringkasan'    => ExpenseService::ringkasan($tahun),
            'tahun'        => $tahun,
            'canCreate'    => can('expense.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('expense.manage');

        $this->renderForm(null, [
            'tanggal'         => today(),
            'tipe'            => 'operasional',
            'judul'           => '',
            'pemohon_user_id' => auth_id(),
            'bidang_id'       => auth_user()['bidang_id'] ?? null,
            'divisi_id'       => auth_user()['divisi_id'] ?? null,
            'cost_center_id'  => null,
            'project_id'      => null,
            'partner_id'      => null,
            'bank_account_id' => null,
            'keterangan'      => '',
            'items'           => [
                ['keterangan' => '', 'coa_id' => 0, 'jumlah' => 0, 'ppn' => 0, 'pph' => 0, 'pajak_type_id' => null, 'no_bukti' => ''],
            ],
        ]);
    }

    public function store(): void
    {
        $this->requireCan('expense.manage');

        $data = ExpenseService::normalisasi($_POST);
        $errors = ExpenseService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = ExpenseService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = ExpenseService::find($id);
        Audit::log('create', 'expense', [
            'ref_table'   => 'expense_requests',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat pengajuan biaya ' . ($row['nomor'] ?? ''),
            'new'         => ['judul' => $data['judul'], 'total' => $row['total'] ?? 0],
        ]);

        flash_success('Pengajuan ' . ($row['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('expense/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('expense.view');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }

        $items = ExpenseService::items((int) $id);
        $status = (string) $row['status'];

        $timeline = [];
        if (!empty($row['created_at'])) {
            $timeline[] = ['label' => 'Dibuat', 'at' => $row['created_at'], 'by' => $row['pemohon_nama'] ?? null, 'icon' => 'edit', 'variant' => 'primary'];
        }
        if (!empty($row['approved_at'])) {
            $timeline[] = ['label' => 'Disetujui', 'at' => $row['approved_at'], 'by' => $row['approved_by_name'] ?? null, 'icon' => 'check', 'variant' => 'sukses'];
        }
        if ($status === 'dibayar' && !empty($row['updated_at'])) {
            $timeline[] = ['label' => 'Dibayar', 'at' => $row['updated_at'], 'by' => null, 'icon' => 'wallet', 'variant' => 'sukses'];
        }

        $this->page('pages/expense/show', [
            'pageTitle'    => 'Biaya ' . $row['nomor'],
            'pageSubtitle' => (string) $row['judul'],
            'breadcrumbs'  => [
                ['label' => 'Pengajuan Biaya', 'url' => url('expense')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('expense')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'items'        => $items,
            'timeline'     => $timeline,
            'banks'        => $this->opsiBank(),
            'canManage'    => can('expense.manage'),
            'canApprove'   => can('expense.approve'),
            'canEdit'      => can('expense.manage') && in_array($status, ['draft', 'rejected'], true),
            'canSubmit'    => can('expense.manage') && in_array($status, ['draft', 'rejected'], true),
            'canPay'       => can('expense.manage') && $status === 'approved',
            'canDelete'    => can('expense.manage') && in_array($status, ['draft', 'rejected'], true),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('expense.manage');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('Pengajuan berstatus ' . ExpenseService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('expense/' . (int) $id);
        }

        $items = ExpenseService::items((int) $id);
        $row['items'] = $items === [] ? [] : $items;

        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('expense.manage');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('Pengajuan berstatus ' . ExpenseService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('expense/' . (int) $id);
        }

        $data = ExpenseService::normalisasi($_POST);
        $errors = ExpenseService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            ExpenseService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'expense', [
            'ref_table'   => 'expense_requests',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah pengajuan biaya ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Pengajuan ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('expense/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('expense.manage');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }

        try {
            ExpenseService::ajukan((int) $id);
            flash_success('Pengajuan ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('expense/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('expense.approve');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }

        try {
            ExpenseService::setujui((int) $id);
            flash_success('Pengajuan ' . $row['nomor'] . ' disetujui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('expense/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('expense.approve');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }

        try {
            ExpenseService::tolak((int) $id, trim((string) input('alasan', '')));
            flash_success('Pengajuan ' . $row['nomor'] . ' ditolak.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('expense/' . (int) $id);
    }

    public function pay(string $id): void
    {
        $this->requireCan('expense.manage');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }

        $bankId = (int) input('bank_account_id', 0);
        $tanggal = trim((string) input('tanggal', '')) ?: today();

        try {
            if ($bankId <= 0) {
                throw new \RuntimeException('Pilih rekening pembayaran.');
            }
            $journalId = ExpenseService::bayar((int) $id, $bankId, $tanggal);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Pembayaran tercatat, jurnal ' . ($journal['nomor'] ?? '') . ' terposting.');
        } catch (\Throwable $e) {
            flash_error('Pembayaran gagal: ' . $e->getMessage());
        }
        redirect('expense/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('expense.manage');

        $row = ExpenseService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Pengajuan biaya tidak ditemukan.');
        }

        try {
            ExpenseService::hapus((int) $id);
            Audit::log('delete', 'expense', [
                'ref_table'   => 'expense_requests',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus pengajuan biaya ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('Pengajuan ' . $row['nomor'] . ' dihapus.');
            redirect('expense');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('expense/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/expense/form', [
            'pageTitle'    => $id === null ? 'Pengajuan Biaya Baru' : 'Ubah Pengajuan Biaya',
            'pageSubtitle' => 'Rincian biaya, PPN masukan, dan potongan PPh dihitung otomatis.',
            'breadcrumbs'  => [
                ['label' => 'Pengajuan Biaya', 'url' => url('expense')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('expense') : url('expense/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'users'        => $this->opsiUser(),
            'banks'        => $this->opsiBank(),
            'coa'          => $this->opsiCoaPosting(),
            'pajak'        => $this->opsiPajak(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'costCenters'  => $this->opsiCostCenter(),
            'projects'     => $this->opsiProject(),
            'partners'     => $this->opsiPartner(),
        ]);
    }
}
