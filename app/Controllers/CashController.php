<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\CashService;
use Lju\Services\PeriodService;

/**
 * Kas & bank: daftar transaksi, input, alur persetujuan, dan posting otomatis.
 */
final class CashController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('cash.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $tipe = (string) input('tipe', '');
        $bankId = (int) input('bank', 0);
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(t.nomor LIKE ? OR t.deskripsi LIKE ? OR t.referensi LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($status !== '' && array_key_exists($status, CashService::STATUS)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($tipe !== '' && array_key_exists($tipe, CashService::TIPE)) {
            $where[] = 't.tipe = ?';
            $params[] = $tipe;
        }
        if ($bankId > 0) {
            $where[] = 't.bank_account_id = ?';
            $params[] = $bankId;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM cash_transactions t WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT t.*, b.nama AS bank_nama, b.kode AS bank_kode, b2.nama AS bank_tujuan_nama,
                    c.kode AS coa_kode, c.nama AS coa_nama, p.nama AS periode_nama,
                    j.nomor AS journal_nomor
             FROM cash_transactions t
             LEFT JOIN ms_bank_accounts b ON b.id = t.bank_account_id
             LEFT JOIN ms_bank_accounts b2 ON b2.id = t.bank_account_tujuan_id
             LEFT JOIN coa_accounts c ON c.id = t.coa_lawan_id
             LEFT JOIN fiscal_periods p ON p.id = t.periode_id
             LEFT JOIN journal_entries j ON j.id = t.journal_id
             WHERE ' . $whereSql . '
             ORDER BY t.tanggal DESC, t.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $tahun = (int) (periode_aktif()['tahun'] ?? date('Y'));

        $this->page('pages/cash/index', [
            'pageTitle'    => 'Kas & Bank',
            'pageSubtitle' => 'Kas masuk, kas keluar, dan transfer antar rekening dengan jurnal otomatis.',
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => null],
            ],
            'pageActions'  => can('cash.manage')
                ? '<a class="btn" href="' . e(url('cash/reconciliation')) . '">' . icon('scale', 15) . ' Rekonsiliasi</a>'
                  . '<a class="btn btn--primary" href="' . e(url('cash/create')) . '">' . icon('plus', 15) . ' Transaksi Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'tipe'         => $tipe,
            'bankId'       => $bankId,
            'banks'        => $this->opsiBank(),
            'ringkasan'    => CashService::ringkasan($tahun),
            'tahun'        => $tahun,
            'canCreate'    => can('cash.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('cash.manage');

        $this->renderForm(null, [
            'tanggal'                => today(),
            'tipe'                   => (string) input('tipe', 'keluar'),
            'bank_account_id'        => 0,
            'bank_account_tujuan_id' => null,
            'coa_lawan_id'           => 0,
            'partner_id'             => null,
            'project_id'             => null,
            'bidang_id'              => null,
            'divisi_id'              => null,
            'cost_center_id'         => null,
            'jumlah'                 => 0.0,
            'referensi'              => '',
            'deskripsi'              => '',
        ]);
    }

    public function store(): void
    {
        $this->requireCan('cash.manage');

        $data = CashService::normalisasi($_POST);
        $errors = CashService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            keep_old($_POST);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = CashService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = CashService::find($id);
        Audit::log('create', 'cash', [
            'ref_table'   => 'cash_transactions',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat transaksi kas ' . ($row['nomor'] ?? ''),
            'new'         => $data,
        ]);

        clear_old();
        flash_success('Transaksi ' . ($row['nomor'] ?? '') . ' berhasil disimpan sebagai draft.');
        redirect('cash/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('cash.view');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }

        $status = (string) $row['status'];
        $periodeOpen = (string) ($row['periode_status'] ?? 'open') === 'open';

        $timeline = [];
        if (!empty($row['created_at'])) {
            $timeline[] = ['label' => 'Dibuat', 'at' => $row['created_at'], 'by' => $row['created_by_name'] ?? null, 'icon' => 'edit', 'variant' => 'primary'];
        }
        if (!empty($row['approved_at'])) {
            $timeline[] = ['label' => 'Disetujui', 'at' => $row['approved_at'], 'by' => $row['approved_by_name'] ?? null, 'icon' => 'check', 'variant' => 'sukses'];
        }
        if (!empty($row['posted_at'])) {
            $timeline[] = ['label' => 'Diposting', 'at' => $row['posted_at'], 'by' => $row['approved_by_name'] ?? null, 'icon' => 'check-circle', 'variant' => 'sukses'];
        }

        $this->page('pages/cash/show', [
            'pageTitle'    => 'Transaksi ' . $row['nomor'],
            'pageSubtitle' => (string) $row['deskripsi'],
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => url('cash')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('cash')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'timeline'     => $timeline,
            'periodeOpen'  => $periodeOpen,
            'canEdit'      => can('cash.manage') && in_array($status, ['draft', 'rejected'], true),
            'canSubmit'    => can('cash.manage') && in_array($status, ['draft', 'rejected'], true),
            'canApprove'   => can('cash.approve') && $status === 'submitted',
            'canPost'      => can('cash.post') && $status === 'approved' && $periodeOpen,
            'canDelete'    => can('cash.manage') && in_array($status, ['draft', 'rejected'], true),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('Transaksi berstatus ' . CashService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('cash/' . (int) $id);
        }

        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('Transaksi berstatus ' . CashService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('cash/' . (int) $id);
        }

        $data = CashService::normalisasi($_POST);
        $errors = CashService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            CashService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'cash', [
            'ref_table'   => 'cash_transactions',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah transaksi kas ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Transaksi ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('cash/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }

        try {
            CashService::ajukan((int) $id);
            flash_success('Transaksi ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('cash/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('cash.approve');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }

        try {
            CashService::setujui((int) $id);
            flash_success('Transaksi ' . $row['nomor'] . ' disetujui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('cash/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('cash.approve');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }

        try {
            CashService::tolak((int) $id, trim((string) input('alasan', '')));
            flash_success('Transaksi ' . $row['nomor'] . ' ditolak.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('cash/' . (int) $id);
    }

    public function post(string $id): void
    {
        $this->requireCan('cash.post');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }

        try {
            $journalId = CashService::posting((int) $id);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Transaksi ' . $row['nomor'] . ' diposting ke jurnal ' . ($journal['nomor'] ?? '') . '.');
        } catch (\Throwable $e) {
            flash_error('Gagal posting: ' . $e->getMessage());
        }
        redirect('cash/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = CashService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Transaksi kas tidak ditemukan.');
        }

        try {
            CashService::hapus((int) $id);
            Audit::log('delete', 'cash', [
                'ref_table'   => 'cash_transactions',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus transaksi kas ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('Transaksi ' . $row['nomor'] . ' dihapus.');
            redirect('cash');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('cash/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/cash/form', [
            'pageTitle'    => $id === null ? 'Transaksi Kas Baru' : 'Ubah Transaksi Kas',
            'pageSubtitle' => 'Catat perpindahan dana kas/bank dengan akun lawan yang tepat.',
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => url('cash')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('cash') : url('cash/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'banks'        => $this->opsiBank(),
            'coa'          => $this->opsiCoaPosting(),
            'partners'     => $this->opsiPartner(),
            'projects'     => $this->opsiProject(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'costCenters'  => $this->opsiCostCenter(),
            'periods'      => PeriodService::years(),
        ]);
    }
}
