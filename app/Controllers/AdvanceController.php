<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\AdvanceService;

/**
 * Uang muka kegiatan: pengajuan, persetujuan, pencairan,
 * pertanggungjawaban, dan penyelesaian.
 */
final class AdvanceController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('advance.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(a.nomor LIKE ? OR a.keperluan LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%');
        }
        if ($status !== '' && array_key_exists($status, AdvanceService::STATUS)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM cash_advances a WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT a.*, u.name AS pemohon_nama, b.nama AS bank_nama, bd.nama AS bidang_nama,
                    p.nama AS periode_nama, j.nomor AS journal_nomor
             FROM cash_advances a
             LEFT JOIN app_users u ON u.id = a.pemohon_user_id
             LEFT JOIN ms_bank_accounts b ON b.id = a.bank_account_id
             LEFT JOIN ms_bidang bd ON bd.id = a.bidang_id
             LEFT JOIN fiscal_periods p ON p.id = a.periode_id
             LEFT JOIN journal_entries j ON j.id = a.journal_id
             WHERE ' . $whereSql . '
             ORDER BY a.tanggal DESC, a.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $tahun = (int) (periode_aktif()['tahun'] ?? date('Y'));
        $sum = Db::one(
            'SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(jumlah_diajukan),0) AS diajukan,
                    COALESCE(SUM(jumlah_dicairkan),0) AS dicairkan,
                    COALESCE(SUM(CASE WHEN status IN (\'cair\',\'pertanggungjawaban\') THEN jumlah_dicairkan - jumlah_dipertanggungjawabkan ELSE 0 END),0) AS beredar
             FROM cash_advances WHERE YEAR(tanggal) = ?',
            [$tahun]
        );

        $this->page('pages/advance/index', [
            'pageTitle'    => 'Uang Muka Kegiatan',
            'pageSubtitle' => 'Pengajuan, pencairan, dan pertanggungjawaban uang muka.',
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => null],
                ['label' => 'Uang Muka', 'url' => null],
            ],
            'pageActions'  => can('advance.manage')
                ? '<a class="btn btn--primary" href="' . e(url('advance/create')) . '">' . icon('plus', 15) . ' Pengajuan Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'sum'          => $sum,
            'tahun'        => $tahun,
            'canCreate'    => can('advance.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('advance.manage');

        $user = auth_user();

        $this->renderForm(null, [
            'tanggal'         => today(),
            'pemohon_user_id' => auth_id(),
            'bagian'          => (string) ($user['nama_bidang'] ?? ''),
            'bidang_id'       => $user['bidang_id'] ?? null,
            'divisi_id'       => $user['divisi_id'] ?? null,
            'cost_center_id'  => null,
            'project_id'      => null,
            'keperluan'       => '',
            'jumlah_diajukan' => 0.0,
            'bank_account_id' => null,
            'catatan'         => '',
        ]);
    }

    public function store(): void
    {
        $this->requireCan('advance.manage');

        $data = AdvanceService::normalisasi($_POST);
        $errors = AdvanceService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            keep_old($_POST);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = AdvanceService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = AdvanceService::find($id);
        Audit::log('create', 'advance', [
            'ref_table'   => 'cash_advances',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat pengajuan uang muka ' . ($row['nomor'] ?? ''),
            'new'         => $data,
        ]);

        clear_old();
        flash_success('Pengajuan ' . ($row['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('advance/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('advance.view');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        $items = AdvanceService::items((int) $id);
        $status = (string) $row['status'];

        $timeline = [];
        if (!empty($row['created_at'])) {
            $timeline[] = ['label' => 'Diajukan', 'at' => $row['created_at'], 'by' => $row['pemohon_nama'] ?? null, 'icon' => 'edit', 'variant' => 'primary'];
        }
        if (!empty($row['approved_at'])) {
            $timeline[] = ['label' => 'Disetujui', 'at' => $row['approved_at'], 'by' => $row['approved_by_name'] ?? null, 'icon' => 'check', 'variant' => 'sukses'];
        }
        if (!empty($row['tanggal_cair'])) {
            $timeline[] = ['label' => 'Dicairkan', 'at' => $row['tanggal_cair'], 'by' => $row['bank_nama'] ?? null, 'icon' => 'wallet', 'variant' => 'info'];
        }
        if ($items !== []) {
            $timeline[] = ['label' => 'Pertanggungjawaban', 'at' => $row['updated_at'] ?? null, 'by' => null, 'icon' => 'receipt', 'variant' => 'warning'];
        }
        if ($status === 'selesai' && !empty($row['journal_settlement_nomor'])) {
            $timeline[] = ['label' => 'Selesai', 'at' => $row['updated_at'] ?? null, 'by' => null, 'icon' => 'check-circle', 'variant' => 'sukses'];
        }

        $totalItem = 0.0;
        foreach ($items as $item) {
            $totalItem += (float) $item['jumlah'];
        }

        $this->page('pages/advance/show', [
            'pageTitle'    => 'Uang Muka ' . $row['nomor'],
            'pageSubtitle' => (string) $row['keperluan'],
            'breadcrumbs'  => [
                ['label' => 'Uang Muka', 'url' => url('advance')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('advance')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'items'        => $items,
            'totalItem'    => $totalItem,
            'timeline'     => $timeline,
            'banks'        => $this->opsiBank(),
            'coa'          => $this->opsiCoaPosting(),
            'pajak'        => $this->opsiPajak(),
            'canManage'    => can('advance.manage'),
            'canApprove'   => can('advance.approve'),
            'canEdit'      => can('advance.manage') && in_array($status, ['draft', 'rejected'], true),
            'canSubmit'    => can('advance.manage') && in_array($status, ['draft', 'rejected'], true),
            'canCair'      => can('advance.manage') && $status === 'approved',
            'canItems'     => can('advance.manage') && in_array($status, ['cair', 'pertanggungjawaban'], true),
            'canSettle'    => can('advance.manage') && in_array($status, ['cair', 'pertanggungjawaban'], true) && $items !== [],
            'canDelete'    => can('advance.manage') && in_array($status, ['draft', 'rejected'], true),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('Pengajuan berstatus ' . AdvanceService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('advance/' . (int) $id);
        }

        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'rejected'], true)) {
            flash_error('Pengajuan berstatus ' . AdvanceService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('advance/' . (int) $id);
        }

        $data = AdvanceService::normalisasi($_POST);
        $errors = AdvanceService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            AdvanceService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'advance', [
            'ref_table'   => 'cash_advances',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah pengajuan uang muka ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Pengajuan ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('advance/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        try {
            AdvanceService::ajukan((int) $id);
            flash_success('Pengajuan ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('advance/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('advance.approve');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        $jumlah = trim((string) input('jumlah_disetujui', ''));
        $nilai = $jumlah === '' ? null : \Lju\Services\JournalService::uang($jumlah);

        try {
            AdvanceService::setujui((int) $id, $nilai);
            flash_success('Pengajuan ' . $row['nomor'] . ' disetujui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('advance/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('advance.approve');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        try {
            AdvanceService::tolak((int) $id, trim((string) input('alasan', '')));
            flash_success('Pengajuan ' . $row['nomor'] . ' ditolak.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('advance/' . (int) $id);
    }

    public function cair(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        $bankId = (int) input('bank_account_id', 0);
        $tanggal = trim((string) input('tanggal', '')) ?: today();
        $jumlah = trim((string) input('jumlah', ''));
        $nilai = $jumlah === '' ? null : \Lju\Services\JournalService::uang($jumlah);

        try {
            if ($bankId <= 0) {
                throw new \RuntimeException('Pilih rekening sumber pencairan.');
            }
            $journalId = AdvanceService::cairkan((int) $id, $bankId, $tanggal, $nilai);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Dana uang muka dicairkan, jurnal ' . ($journal['nomor'] ?? '') . ' terposting.');
        } catch (\Throwable $e) {
            flash_error('Pencairan gagal: ' . $e->getMessage());
        }
        redirect('advance/' . (int) $id);
    }

    public function items(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        $input = (array) ($_POST['items'] ?? []);
        $items = [];
        foreach ($input as $item) {
            $items[] = [
                'tanggal'       => trim((string) ($item['tanggal'] ?? '')) ?: null,
                'keterangan'    => trim((string) ($item['keterangan'] ?? '')),
                'coa_id'        => (int) ($item['coa_id'] ?? 0),
                'jumlah'        => $item['jumlah'] ?? 0,
                'pajak_type_id' => (int) ($item['pajak_type_id'] ?? 0),
                'nilai_pajak'   => $item['nilai_pajak'] ?? 0,
                'no_bukti'      => trim((string) ($item['no_bukti'] ?? '')),
            ];
        }

        $normal = [];
        foreach ($items as $item) {
            $normal[] = array_merge($item, ['jumlah' => \Lju\Services\JournalService::uang($item['jumlah'])]);
        }

        try {
            AdvanceService::simpanItems((int) $id, $normal);
            flash_success('Rincian pertanggungjawaban disimpan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('advance/' . (int) $id);
    }

    public function settle(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        $bankId = (int) input('bank_account_id', 0) ?: (int) ($row['bank_account_id'] ?? 0);
        $tanggal = trim((string) input('tanggal', '')) ?: today();

        try {
            if ($bankId <= 0) {
                throw new \RuntimeException('Pilih rekening untuk pengembalian/kekurangan dana.');
            }
            $journalId = AdvanceService::selesaikan((int) $id, $bankId, $tanggal);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Pertanggungjawaban selesai, jurnal ' . ($journal['nomor'] ?? '') . ' terposting.');
        } catch (\Throwable $e) {
            flash_error('Penyelesaian gagal: ' . $e->getMessage());
        }
        redirect('advance/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('advance.manage');

        $row = AdvanceService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Uang muka tidak ditemukan.');
        }

        try {
            AdvanceService::hapus((int) $id);
            Audit::log('delete', 'advance', [
                'ref_table'   => 'cash_advances',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus pengajuan uang muka ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('Pengajuan ' . $row['nomor'] . ' dihapus.');
            redirect('advance');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('advance/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/advance/form', [
            'pageTitle'    => $id === null ? 'Pengajuan Uang Muka' : 'Ubah Pengajuan Uang Muka',
            'pageSubtitle' => 'Ajukan dana kegiatan, lalu pertanggungjawabkan setelah pencairan.',
            'breadcrumbs'  => [
                ['label' => 'Uang Muka', 'url' => url('advance')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('advance') : url('advance/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'users'        => $this->opsiUser(),
            'banks'        => $this->opsiBank(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'costCenters'  => $this->opsiCostCenter(),
            'projects'     => $this->opsiProject(),
        ]);
    }
}
