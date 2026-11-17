<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\ArService;

/**
 * Piutang usaha: invoice pelanggan, pengakuan pendapatan, penerimaan, aging.
 */
final class ArController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('ar.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $partnerId = (int) input('partner', 0);
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(a.nomor LIKE ? OR a.deskripsi LIKE ? OR p.nama LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($partnerId > 0) {
            $where[] = 'a.partner_id = ?';
            $params[] = $partnerId;
        }
        if ($status === 'overdue') {
            $where[] = "a.sisa > 0 AND a.status IN ('posted','partial','overdue') AND a.tanggal_jatuh_tempo < CURDATE()";
        } elseif ($status !== '' && array_key_exists($status, ArService::STATUS)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val(
            'SELECT COUNT(*) FROM ar_invoices a LEFT JOIN ms_partners p ON p.id = a.partner_id WHERE ' . $whereSql,
            $params
        );
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT a.*, p.nama AS partner_nama, p.kode AS partner_kode,
                    pr.nama AS project_nama, k.nomor AS contract_nomor, j.nomor AS journal_nomor
             FROM ar_invoices a
             LEFT JOIN ms_partners p ON p.id = a.partner_id
             LEFT JOIN ms_projects pr ON pr.id = a.project_id
             LEFT JOIN ms_contracts k ON k.id = a.contract_id
             LEFT JOIN journal_entries j ON j.id = a.journal_id
             WHERE ' . $whereSql . '
             ORDER BY a.tanggal DESC, a.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $tahun = (int) (periode_aktif()['tahun'] ?? date('Y'));

        $this->page('pages/ar/index', [
            'pageTitle'    => 'Piutang Usaha',
            'pageSubtitle' => 'Invoice pelanggan, pengakuan pendapatan, penerimaan, dan aging piutang.',
            'breadcrumbs'  => [
                ['label' => 'Hutang & Piutang', 'url' => null],
                ['label' => 'Piutang Usaha', 'url' => null],
            ],
            'pageActions'  => can('ar.manage')
                ? '<a class="btn btn--primary" href="' . e(url('ar/create')) . '">' . icon('plus', 15) . ' Invoice Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'partnerId'    => $partnerId,
            'partners'     => $this->opsiPartnerTipe('customer'),
            'ringkasan'    => ArService::ringkasan($tahun),
            'aging'        => ArService::aging(),
            'buckets'      => ArService::buckets(),
            'tahun'        => $tahun,
            'canCreate'    => can('ar.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('ar.manage');

        $this->renderForm(null, [
            'partner_id'          => 0,
            'project_id'          => null,
            'contract_id'         => null,
            'termin_ke'           => null,
            'tanggal'             => today(),
            'tanggal_jatuh_tempo' => date('Y-m-d', strtotime(today() . ' +30 days')),
            'termin_hari'         => 30,
            'deskripsi'           => '',
            'bidang_id'           => null,
            'divisi_id'           => null,
            'cost_center_id'      => null,
            'business_unit_id'    => null,
            'items'               => [
                ['keterangan' => '', 'coa_id' => 0, 'jumlah' => 0, 'ppn' => 0],
            ],
        ]);
    }

    public function store(): void
    {
        $this->requireCan('ar.manage');

        $data = ArService::normalisasi($_POST);
        $errors = ArService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = ArService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = ArService::find($id);
        Audit::log('create', 'ar', [
            'ref_table'   => 'ar_invoices',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat invoice pelanggan ' . ($row['nomor'] ?? ''),
            'new'         => ['partner_id' => $data['partner_id'], 'total' => $row['total'] ?? 0],
        ]);

        flash_success('Invoice ' . ($row['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('ar/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('ar.view');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        $items = ArService::items((int) $id);
        $payments = ArService::payments((int) $id);
        $status = ArService::statusEfektif($row);

        $timeline = [];
        if (!empty($row['created_at'])) {
            $timeline[] = ['label' => 'Dibuat', 'at' => $row['created_at'], 'by' => $row['created_by_name'] ?? null, 'icon' => 'edit', 'variant' => 'primary'];
        }
        if (!empty($row['approved_at'])) {
            $timeline[] = ['label' => 'Disetujui', 'at' => $row['approved_at'], 'by' => $row['approved_by_name'] ?? null, 'icon' => 'check', 'variant' => 'sukses'];
        }
        if (!empty($row['journal_id'])) {
            $timeline[] = ['label' => 'Diposting', 'at' => $row['updated_at'] ?? null, 'by' => null, 'icon' => 'book', 'variant' => 'info'];
        }
        foreach ($payments as $payment) {
            $timeline[] = [
                'label' => 'Penerimaan ' . rupiah((float) $payment['jumlah']),
                'at' => $payment['tanggal'],
                'by' => $payment['bank_nama'] ?? null,
                'icon' => 'wallet',
                'variant' => 'sukses',
            ];
        }

        $this->page('pages/ar/show', [
            'pageTitle'    => 'Invoice ' . $row['nomor'],
            'pageSubtitle' => (string) ($row['partner_nama'] ?? ''),
            'breadcrumbs'  => [
                ['label' => 'Piutang Usaha', 'url' => url('ar')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('ar')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'items'        => $items,
            'payments'     => $payments,
            'statusEfektif' => $status,
            'timeline'     => $timeline,
            'banks'        => $this->opsiBank(),
            'metode'       => ArService::METODE,
            'canEdit'      => can('ar.manage') && (string) $row['status'] === 'draft',
            'canSubmit'    => can('ar.manage') && (string) $row['status'] === 'draft',
            'canApprove'   => can('ar.approve') && (string) $row['status'] === 'submitted',
            'canPost'      => can('ar.manage') && (string) $row['status'] === 'approved',
            'canPay'       => can('ar.manage') && in_array($status, ['posted', 'partial', 'overdue'], true),
            'canDelete'    => can('ar.manage') && (string) $row['status'] === 'draft',
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Invoice berstatus ' . ArService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('ar/' . (int) $id);
        }

        $row['items'] = ArService::items((int) $id);
        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Invoice berstatus ' . ArService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('ar/' . (int) $id);
        }

        $data = ArService::normalisasi($_POST);
        $errors = ArService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            ArService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'ar', [
            'ref_table'   => 'ar_invoices',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah invoice pelanggan ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Invoice ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('ar/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        try {
            ArService::ajukan((int) $id);
            flash_success('Invoice ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('ar/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('ar.approve');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        try {
            ArService::setujui((int) $id);
            flash_success('Invoice ' . $row['nomor'] . ' disetujui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('ar/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('ar.approve');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        try {
            ArService::tolak((int) $id, trim((string) input('alasan', '')));
            flash_success('Invoice ' . $row['nomor'] . ' dikembalikan ke draft.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('ar/' . (int) $id);
    }

    public function post(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        try {
            $journalId = ArService::posting((int) $id);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Invoice diposting ke jurnal ' . ($journal['nomor'] ?? '') . '.');
        } catch (\Throwable $e) {
            flash_error('Gagal posting: ' . $e->getMessage());
        }
        redirect('ar/' . (int) $id);
    }

    public function terima(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        try {
            $journalId = ArService::terima((int) $id, [
                'tanggal'         => input('tanggal', today()),
                'bank_account_id' => (int) input('bank_account_id', 0),
                'jumlah'          => $_POST['jumlah'] ?? 0,
                'potongan_pph'    => $_POST['potongan_pph'] ?? 0,
                'metode'          => (string) input('metode', 'transfer'),
                'referensi'       => (string) input('referensi', ''),
            ]);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Penerimaan tercatat, jurnal ' . ($journal['nomor'] ?? '') . ' terposting.');
        } catch (\Throwable $e) {
            flash_error('Penerimaan gagal: ' . $e->getMessage());
        }
        redirect('ar/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('ar.manage');

        $row = ArService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Invoice pelanggan tidak ditemukan.');
        }

        try {
            ArService::hapus((int) $id);
            Audit::log('delete', 'ar', [
                'ref_table'   => 'ar_invoices',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus invoice pelanggan ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('Invoice ' . $row['nomor'] . ' dihapus.');
            redirect('ar');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('ar/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/ar/form', [
            'pageTitle'    => $id === null ? 'Invoice Pelanggan Baru' : 'Ubah Invoice Pelanggan',
            'pageSubtitle' => 'Terbitkan invoice beserta pengakuan pendapatan dan PPN keluaran.',
            'breadcrumbs'  => [
                ['label' => 'Piutang Usaha', 'url' => url('ar')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('ar') : url('ar/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'partners'     => $this->opsiPartnerTipe('customer'),
            'coa'          => $this->opsiCoaPosting(),
            'coaPendapatan' => $this->opsiCoaPendapatan(),
            'projects'     => $this->opsiProject(),
            'contracts'    => $this->opsiContract(),
            'businessUnits' => $this->opsiBusinessUnit(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'costCenters'  => $this->opsiCostCenter(),
            'ppnTarif'     => (float) (Db::val("SELECT tarif FROM tax_types WHERE jenis = 'ppn' ORDER BY id LIMIT 1") ?? 11),
        ]);
    }
}
