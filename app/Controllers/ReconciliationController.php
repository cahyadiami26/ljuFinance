<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\BankReconService;

/**
 * Rekonsiliasi bank: bandingkan saldo buku dengan rekening koran.
 */
final class ReconciliationController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('cash.view');

        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);
        $bankId = (int) input('bank', 0);

        $where = ['1 = 1'];
        $params = [];
        if ($bankId > 0) {
            $where[] = 'r.bank_account_id = ?';
            $params[] = $bankId;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Db::val('SELECT COUNT(*) FROM bank_reconciliations r WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT r.*, b.nama AS bank_nama, b.kode AS bank_kode, b.nama_bank, b.nomor_rekening,
                    p.nama AS periode_nama, u.name AS created_by_name
             FROM bank_reconciliations r
             JOIN ms_bank_accounts b ON b.id = r.bank_account_id
             LEFT JOIN fiscal_periods p ON p.id = r.periode_id
             LEFT JOIN app_users u ON u.id = r.created_by
             WHERE ' . $whereSql . '
             ORDER BY r.tanggal_akhir DESC, r.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $this->page('pages/cash/reconciliation-index', [
            'pageTitle'    => 'Rekonsiliasi Bank',
            'pageSubtitle' => 'Cocokkan mutasi buku besar dengan rekening koran setiap rekening.',
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => url('cash')],
                ['label' => 'Rekonsiliasi', 'url' => null],
            ],
            'pageActions'  => can('cash.manage')
                ? '<a class="btn btn--primary" href="' . e(url('cash/reconciliation/create')) . '">' . icon('plus', 15) . ' Rekonsiliasi Baru</a>'
                : '',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'bankId'       => $bankId,
            'banks'        => $this->opsiBank(),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('cash.manage');

        $awal = date('Y-m-01');

        $this->renderForm([
            'bank_account_id' => 0,
            'tanggal_mulai'   => $awal,
            'tanggal_akhir'   => today(),
            'saldo_bank'      => 0.0,
            'catatan'         => '',
            'items'           => [],
        ]);
    }

    public function store(): void
    {
        $this->requireCan('cash.manage');

        $data = BankReconService::normalisasi($_POST);
        $errors = BankReconService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm($data, $errors);
            return;
        }

        try {
            $id = BankReconService::simpan($data);
        } catch (\Throwable $e) {
            $this->renderForm($data, ['general' => $e->getMessage()]);
            return;
        }

        flash_success('Rekonsiliasi dibuat. Cocokkan baris buku dengan rekening koran.');
        redirect('cash/reconciliation/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('cash.view');

        $row = BankReconService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Rekonsiliasi tidak ditemukan.');
        }

        $items = BankReconService::items((int) $id);
        $buku = [];
        $bank = [];
        foreach ($items as $item) {
            if ((string) $item['sumber'] === 'buku') {
                $buku[] = $item;
            } else {
                $bank[] = $item;
            }
        }

        $this->page('pages/cash/reconciliation-show', [
            'pageTitle'    => 'Rekonsiliasi ' . $row['bank_nama'],
            'pageSubtitle' => 'Periode ' . ($row['periode_nama'] ?? '-') . ' · ' . tanggal($row['tanggal_mulai'], 'd M Y') . ' – ' . tanggal($row['tanggal_akhir'], 'd M Y'),
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => url('cash')],
                ['label' => 'Rekonsiliasi', 'url' => url('cash/reconciliation')],
                ['label' => 'RECON-' . (int) $row['id'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('cash/reconciliation')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'buku'         => $buku,
            'bank'         => $bank,
            'canManage'    => can('cash.manage'),
        ]);
    }

    public function match(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = BankReconService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Rekonsiliasi tidak ditemukan.');
        }

        try {
            BankReconService::cocokkan((int) $id, (array) ($_POST['matched'] ?? []));
            flash_success('Baris yang dipilih ditandai cocok.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('cash/reconciliation/' . (int) $id);
    }

    public function finish(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = BankReconService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Rekonsiliasi tidak ditemukan.');
        }

        try {
            BankReconService::selesaikan((int) $id, trim((string) input('catatan', '')));
            flash_success('Rekonsiliasi diselesaikan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('cash/reconciliation/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('cash.manage');

        $row = BankReconService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Rekonsiliasi tidak ditemukan.');
        }

        try {
            BankReconService::hapus((int) $id);
            Audit::log('delete', 'reconciliation', [
                'ref_table'   => 'bank_reconciliations',
                'ref_id'      => (int) $id,
                'ref_code'    => 'RECON-' . (int) $id,
                'description' => 'Hapus rekonsiliasi bank ' . $row['bank_nama'],
            ]);
            flash_success('Rekonsiliasi dihapus.');
            redirect('cash/reconciliation');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('cash/reconciliation/' . (int) $id);
        }
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(array $row, array $errors = []): void
    {
        $this->page('pages/cash/reconciliation-form', [
            'pageTitle'    => 'Rekonsiliasi Bank Baru',
            'pageSubtitle' => 'Masukkan saldo rekening koran dan baris mutasi yang belum tercatat di buku.',
            'breadcrumbs'  => [
                ['label' => 'Kas & Bank', 'url' => url('cash')],
                ['label' => 'Rekonsiliasi', 'url' => url('cash/reconciliation')],
                ['label' => 'Baru', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('cash/reconciliation')) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'errors'       => $errors === [] ? errors() : $errors,
            'banks'        => $this->opsiBank(),
        ]);
    }
}
