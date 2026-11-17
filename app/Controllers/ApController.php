<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\ApService;

/**
 * Hutang usaha: tagihan vendor, persetujuan, posting, pembayaran, aging.
 */
final class ApController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('ap.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $partnerId = (int) input('partner', 0);
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(a.nomor LIKE ? OR a.nomor_supplier LIKE ? OR p.nama LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($partnerId > 0) {
            $where[] = 'a.partner_id = ?';
            $params[] = $partnerId;
        }
        if ($status === 'overdue') {
            $where[] = "a.sisa > 0 AND a.status IN ('posted','partial','overdue') AND a.tanggal_jatuh_tempo < CURDATE()";
        } elseif ($status !== '' && array_key_exists($status, ApService::STATUS)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val(
            'SELECT COUNT(*) FROM ap_invoices a LEFT JOIN ms_partners p ON p.id = a.partner_id WHERE ' . $whereSql,
            $params
        );
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT a.*, p.nama AS partner_nama, p.kode AS partner_kode,
                    pr.nama AS project_nama, j.nomor AS journal_nomor
             FROM ap_invoices a
             LEFT JOIN ms_partners p ON p.id = a.partner_id
             LEFT JOIN ms_projects pr ON pr.id = a.project_id
             LEFT JOIN journal_entries j ON j.id = a.journal_id
             WHERE ' . $whereSql . '
             ORDER BY a.tanggal DESC, a.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $tahun = (int) (periode_aktif()['tahun'] ?? date('Y'));

        $this->page('pages/ap/index', [
            'pageTitle'    => 'Hutang Usaha',
            'pageSubtitle' => 'Tagihan vendor, persetujuan pembayaran, dan aging hutang.',
            'breadcrumbs'  => [
                ['label' => 'Hutang & Piutang', 'url' => null],
                ['label' => 'Hutang Usaha', 'url' => null],
            ],
            'pageActions'  => can('ap.manage')
                ? '<a class="btn btn--primary" href="' . e(url('ap/create')) . '">' . icon('plus', 15) . ' Tagihan Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'partnerId'    => $partnerId,
            'partners'     => $this->opsiPartnerTipe('vendor'),
            'ringkasan'    => ApService::ringkasan($tahun),
            'aging'        => ApService::aging(),
            'buckets'      => ApService::buckets(),
            'tahun'        => $tahun,
            'canCreate'    => can('ap.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('ap.manage');

        $this->renderForm(null, [
            'nomor_supplier'      => '',
            'partner_id'          => 0,
            'tanggal'             => today(),
            'tanggal_jatuh_tempo' => date('Y-m-d', strtotime(today() . ' +30 days')),
            'termin_hari'         => 30,
            'termin_ke'           => null,
            'project_id'          => null,
            'bidang_id'           => null,
            'divisi_id'           => null,
            'cost_center_id'      => null,
            'keterangan'          => '',
            'items'               => [
                ['keterangan' => '', 'coa_id' => 0, 'jumlah' => 0, 'ppn' => 0],
            ],
        ]);
    }

    public function store(): void
    {
        $this->requireCan('ap.manage');

        $data = ApService::normalisasi($_POST);
        $errors = ApService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = ApService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = ApService::find($id);
        Audit::log('create', 'ap', [
            'ref_table'   => 'ap_invoices',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['nomor'] ?? ''),
            'description' => 'Buat tagihan vendor ' . ($row['nomor'] ?? ''),
            'new'         => ['partner_id' => $data['partner_id'], 'total' => $row['total'] ?? 0],
        ]);

        flash_success('Tagihan ' . ($row['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('ap/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('ap.view');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        $items = ApService::items((int) $id);
        $payments = ApService::payments((int) $id);
        $status = ApService::statusEfektif($row);

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
                'label' => 'Pembayaran ' . rupiah((float) $payment['jumlah']),
                'at' => $payment['tanggal'],
                'by' => $payment['bank_nama'] ?? null,
                'icon' => 'wallet',
                'variant' => 'sukses',
            ];
        }

        $this->page('pages/ap/show', [
            'pageTitle'    => 'Tagihan ' . $row['nomor'],
            'pageSubtitle' => (string) ($row['partner_nama'] ?? ''),
            'breadcrumbs'  => [
                ['label' => 'Hutang Usaha', 'url' => url('ap')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('ap')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'items'        => $items,
            'payments'     => $payments,
            'statusEfektif' => $status,
            'timeline'     => $timeline,
            'banks'        => $this->opsiBank(),
            'metode'       => ApService::METODE,
            'canEdit'      => can('ap.manage') && (string) $row['status'] === 'draft',
            'canSubmit'    => can('ap.manage') && (string) $row['status'] === 'draft',
            'canApprove'   => can('ap.approve') && (string) $row['status'] === 'submitted',
            'canPost'      => can('ap.manage') && (string) $row['status'] === 'approved',
            'canPay'       => can('ap.pay') && in_array($status, ['posted', 'partial', 'overdue'], true),
            'canDelete'    => can('ap.manage') && (string) $row['status'] === 'draft',
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('ap.manage');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Tagihan berstatus ' . ApService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('ap/' . (int) $id);
        }

        $row['items'] = ApService::items((int) $id);
        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('ap.manage');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Tagihan berstatus ' . ApService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('ap/' . (int) $id);
        }

        $data = ApService::normalisasi($_POST);
        $errors = ApService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            ApService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'ap', [
            'ref_table'   => 'ap_invoices',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Ubah tagihan vendor ' . $row['nomor'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Tagihan ' . $row['nomor'] . ' berhasil diperbarui.');
        redirect('ap/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('ap.manage');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        try {
            ApService::ajukan((int) $id);
            flash_success('Tagihan ' . $row['nomor'] . ' diajukan untuk persetujuan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('ap/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('ap.approve');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        try {
            ApService::setujui((int) $id);
            flash_success('Tagihan ' . $row['nomor'] . ' disetujui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('ap/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('ap.approve');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        try {
            ApService::tolak((int) $id, trim((string) input('alasan', '')));
            flash_success('Tagihan ' . $row['nomor'] . ' dikembalikan ke draft.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('ap/' . (int) $id);
    }

    public function post(string $id): void
    {
        $this->requireCan('ap.manage');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        try {
            $journalId = ApService::posting((int) $id);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Tagihan diposting ke jurnal ' . ($journal['nomor'] ?? '') . '.');
        } catch (\Throwable $e) {
            flash_error('Gagal posting: ' . $e->getMessage());
        }
        redirect('ap/' . (int) $id);
    }

    public function pay(string $id): void
    {
        $this->requireCan('ap.pay');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        try {
            $journalId = ApService::bayar((int) $id, [
                'tanggal'         => input('tanggal', today()),
                'bank_account_id' => (int) input('bank_account_id', 0),
                'jumlah'          => $_POST['jumlah'] ?? 0,
                'potongan_pph'    => $_POST['potongan_pph'] ?? 0,
                'metode'          => (string) input('metode', 'transfer'),
                'referensi'       => (string) input('referensi', ''),
            ]);
            $journal = Db::one('SELECT nomor FROM journal_entries WHERE id = ?', [$journalId]);
            flash_success('Pembayaran tercatat, jurnal ' . ($journal['nomor'] ?? '') . ' terposting.');
        } catch (\Throwable $e) {
            flash_error('Pembayaran gagal: ' . $e->getMessage());
        }
        redirect('ap/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('ap.manage');

        $row = ApService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Tagihan vendor tidak ditemukan.');
        }

        try {
            ApService::hapus((int) $id);
            Audit::log('delete', 'ap', [
                'ref_table'   => 'ap_invoices',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Hapus tagihan vendor ' . $row['nomor'],
                'old'         => $row,
            ]);
            flash_success('Tagihan ' . $row['nomor'] . ' dihapus.');
            redirect('ap');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('ap/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/ap/form', [
            'pageTitle'    => $id === null ? 'Tagihan Vendor Baru' : 'Ubah Tagihan Vendor',
            'pageSubtitle' => 'Catat tagihan supplier beserta PPN masukan dan jadwal jatuh tempo.',
            'breadcrumbs'  => [
                ['label' => 'Hutang Usaha', 'url' => url('ap')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('ap') : url('ap/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'partners'     => $this->opsiPartnerTipe('vendor'),
            'coa'          => $this->opsiCoaPosting(),
            'projects'     => $this->opsiProject(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'costCenters'  => $this->opsiCostCenter(),
            'ppnTarif'     => (float) (Db::val("SELECT tarif FROM tax_types WHERE jenis = 'ppn' ORDER BY id LIMIT 1") ?? 11),
        ]);
    }
}
