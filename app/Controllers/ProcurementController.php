<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Flash;
use Lju\Services\InventoryService;
use Lju\Services\ProcurementService;

/**
 * Pengadaan: purchase order, penerimaan barang (GR), dan penarikan tagihan AP.
 */
final class ProcurementController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('procurement.view');

        $q = trim((string) input('q', ''));
        $status = trim((string) input('status', ''));

        $this->page('pages/procurement/index', [
            'pageTitle'    => 'Pengadaan (PO)',
            'pageSubtitle' => 'Purchase order, penerimaan barang, dan tagihan vendor hasil pengadaan.',
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Purchase Order', 'url' => null],
            ],
            'pageActions'  => (can('procurement.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('procurement/create')) . '">' . icon('plus', 15) . ' PO Baru</a>'
                    : '')
                . '<a class="btn" href="' . e(url('inventory')) . '">' . icon('package', 15) . ' Persediaan</a>',
            'rows'         => ProcurementService::list($q, $status),
            'q'            => $q,
            'status'       => $status,
            'statusList'   => ProcurementService::STATUS,
            'ringkasan'    => ProcurementService::ringkasan(),
            'canManage'    => can('procurement.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('procurement.manage');

        $this->renderForm(null, [
            'tanggal'          => today(),
            'partner_id'       => null,
            'project_id'       => null,
            'bidang_id'        => null,
            'divisi_id'        => null,
            'business_unit_id' => null,
            'cost_center_id'   => null,
            'termin_hari'      => 30,
            'catatan'          => '',
            'items'            => [[
                'item_id'    => null,
                'deskripsi'  => '',
                'coa_id'     => null,
                'qty'        => 1,
                'satuan'     => 'unit',
                'harga'      => 0,
                'ppn_persen' => 11,
            ]],
        ]);
    }

    public function store(): void
    {
        $this->requireCan('procurement.manage');

        $data = ProcurementService::normalisasi($_POST);
        $errors = ProcurementService::validasi($data);

        if ($errors !== []) {
            $this->renderForm(null, $data + ['nomor' => ''], $errors);
            return;
        }

        $id = ProcurementService::simpan(null, $data);
        $po = ProcurementService::find($id);
        Audit::log('create', 'procurement.po', [
            'ref_table'   => 'purchase_orders',
            'ref_id'      => $id,
            'ref_code'    => (string) ($po['nomor'] ?? ''),
            'description' => 'Membuat PO ' . (string) ($po['nomor'] ?? '') . ' senilai ' . rupiah((float) $data['total']),
            'new'         => ['partner_id' => $data['partner_id'], 'total' => $data['total']],
        ]);
        Flash::success('Purchase order ' . (string) ($po['nomor'] ?? '') . ' berhasil dibuat.');
        redirect('procurement/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('procurement.view');

        $poId = (int) $id;
        $po = ProcurementService::find($poId);
        if ($po === null) {
            App::abort(404, 'Purchase order tidak ditemukan.');
        }

        $receipts = ProcurementService::receipts($poId);
        $receiptItems = [];
        foreach ($receipts as $receipt) {
            $receiptItems[(int) $receipt['id']] = ProcurementService::receiptItems((int) $receipt['id']);
        }

        $items = ProcurementService::items($poId);
        $qtyTotal = 0.0;
        $qtyDiterima = 0.0;
        foreach ($items as $item) {
            $qtyTotal += (float) $item['qty'];
            $qtyDiterima += (float) $item['diterima_qty'];
        }

        $actions = '<a class="btn" href="' . e(url('procurement')) . '">' . icon('arrow-left', 15) . ' Daftar</a>';
        if (can('procurement.manage') && (string) $po['status'] === 'draft') {
            $actions .= '<a class="btn" href="' . e(url('procurement/' . $poId . '/edit')) . '">' . icon('edit', 15) . ' Edit</a>';
        }
        if (can('procurement.manage') && in_array((string) $po['status'], ['disetujui', 'sebagian'], true)) {
            $actions .= '<a class="btn btn--primary" href="' . e(url('procurement/' . $poId . '/terima')) . '">' . icon('truck', 15) . ' Terima Barang</a>';
        }

        $this->page('pages/procurement/show', [
            'pageTitle'    => 'PO ' . $po['nomor'],
            'pageSubtitle' => (string) $po['partner_nama'] . ' · ' . rupiah((float) $po['total']),
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Purchase Order', 'url' => url('procurement')],
                ['label' => (string) $po['nomor'], 'url' => null],
            ],
            'pageActions'  => $actions,
            'po'           => $po,
            'items'        => $items,
            'receipts'     => $receipts,
            'receiptItems' => $receiptItems,
            'qtyTotal'     => $qtyTotal,
            'qtyDiterima'  => $qtyDiterima,
            'statusList'   => ProcurementService::STATUS,
            'canManage'    => can('procurement.manage'),
            'canApprove'   => can('procurement.approve'),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('procurement.manage');

        $poId = (int) $id;
        $po = ProcurementService::find($poId);
        if ($po === null) {
            App::abort(404, 'Purchase order tidak ditemukan.');
        }
        if ((string) $po['status'] !== 'draft') {
            Flash::error('Hanya PO draft yang dapat diubah.');
            redirect('procurement/' . $poId);
        }

        $po['items'] = ProcurementService::items($poId);
        $this->renderForm($poId, $po);
    }

    public function update(string $id): void
    {
        $this->requireCan('procurement.manage');

        $poId = (int) $id;
        $po = ProcurementService::find($poId);
        if ($po === null) {
            App::abort(404, 'Purchase order tidak ditemukan.');
        }

        $data = ProcurementService::normalisasi($_POST);
        $errors = ProcurementService::validasi($data);

        if ($errors !== []) {
            $this->renderForm($poId, $data + ['nomor' => $po['nomor']], $errors);
            return;
        }

        try {
            ProcurementService::simpan($poId, $data);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('procurement/' . $poId);
        }

        Audit::log('update', 'procurement.po', [
            'ref_table'   => 'purchase_orders',
            'ref_id'      => $poId,
            'ref_code'    => (string) $po['nomor'],
            'description' => 'Memperbarui PO ' . $po['nomor'],
            'new'         => ['total' => $data['total']],
        ]);
        Flash::success('Purchase order berhasil diperbarui.');
        redirect('procurement/' . $poId);
    }

    public function ajukan(string $id): void
    {
        $this->requireCan('procurement.manage');
        $this->aksi((int) $id, 'ajukan', 'diajukan');
    }

    public function setujui(string $id): void
    {
        $this->requireCan('procurement.approve');
        $this->aksi((int) $id, 'setujui', 'disetujui');
    }

    public function batal(string $id): void
    {
        $this->requireCan('procurement.approve');

        $poId = (int) $id;
        $alasan = trim((string) input('alasan', ''));
        try {
            ProcurementService::batal($poId, $alasan);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('procurement/' . $poId);
        }

        $po = ProcurementService::find($poId);
        Audit::log('reject', 'procurement.po', [
            'ref_table'   => 'purchase_orders',
            'ref_id'      => $poId,
            'ref_code'    => (string) ($po['nomor'] ?? ''),
            'description' => 'Membatalkan PO ' . (string) ($po['nomor'] ?? '') . ($alasan !== '' ? ': ' . $alasan : ''),
        ]);
        Flash::success('Purchase order dibatalkan.');
        redirect('procurement/' . $poId);
    }

    public function hapus(string $id): void
    {
        $this->requireCan('procurement.manage');

        $poId = (int) $id;
        $po = ProcurementService::find($poId);
        try {
            ProcurementService::hapus($poId);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('procurement/' . $poId);
        }

        Audit::log('delete', 'procurement.po', [
            'ref_table'   => 'purchase_orders',
            'ref_id'      => $poId,
            'ref_code'    => (string) ($po['nomor'] ?? ''),
            'description' => 'Menghapus draft PO ' . (string) ($po['nomor'] ?? ''),
        ]);
        Flash::success('Draft purchase order dihapus.');
        redirect('procurement');
    }

    public function terimaForm(string $id): void
    {
        $this->requireCan('procurement.manage');

        $poId = (int) $id;
        $po = ProcurementService::find($poId);
        if ($po === null) {
            App::abort(404, 'Purchase order tidak ditemukan.');
        }
        if (!in_array((string) $po['status'], ['disetujui', 'sebagian'], true)) {
            Flash::error('Penerimaan hanya untuk PO yang sudah disetujui.');
            redirect('procurement/' . $poId);
        }

        $this->page('pages/procurement/terima', [
            'pageTitle'    => 'Penerimaan Barang — ' . $po['nomor'],
            'pageSubtitle' => 'Penerimaan menambah stok dan memperbarui harga rata-rata barang.',
            'breadcrumbs'  => [
                ['label' => 'Purchase Order', 'url' => url('procurement')],
                ['label' => (string) $po['nomor'], 'url' => url('procurement/' . $poId)],
                ['label' => 'Terima Barang', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('procurement/' . $poId)) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'po'           => $po,
            'items'        => ProcurementService::items($poId),
            'warehouses'   => InventoryService::warehouses(),
            'defaults'     => ['tanggal' => today(), 'warehouse_id' => null, 'catatan' => ''],
        ]);
    }

    public function terimaStore(string $id): void
    {
        $this->requireCan('procurement.manage');

        $poId = (int) $id;
        try {
            $receiptId = ProcurementService::terima($poId, [
                'tanggal'      => trim((string) input('tanggal', today())) ?: today(),
                'warehouse_id' => (int) input('warehouse_id', 0),
                'catatan'      => trim((string) input('catatan', '')),
                'items'        => (array) ($_POST['items'] ?? []),
            ]);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('procurement/' . $poId . '/terima');
        }

        $po = ProcurementService::find($poId);
        Audit::log('receive', 'procurement.gr', [
            'ref_table'   => 'goods_receipts',
            'ref_id'      => $receiptId,
            'ref_code'    => (string) ($po['nomor'] ?? ''),
            'description' => 'Menerima barang untuk PO ' . (string) ($po['nomor'] ?? ''),
        ]);
        Flash::success('Penerimaan barang dicatat. Stok persediaan telah diperbarui.');
        redirect('procurement/' . $poId);
    }

    public function tagih(string $id): void
    {
        $this->requireCan('procurement.manage');

        $poId = (int) $id;
        try {
            $invoiceId = ProcurementService::tarikTagihan($poId);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('procurement/' . $poId);
        }

        $po = ProcurementService::find($poId);
        Audit::log('create', 'procurement.po', [
            'ref_table'   => 'purchase_orders',
            'ref_id'      => $poId,
            'ref_code'    => (string) ($po['nomor'] ?? ''),
            'description' => 'Menarik tagihan AP dari PO ' . (string) ($po['nomor'] ?? ''),
            'new'         => ['ap_invoice_id' => $invoiceId],
        ]);
        Flash::success('Draft tagihan AP berhasil dibuat. Lengkapi dan posting di modul Hutang.');
        redirect('ap/' . $invoiceId);
    }

    /* --------------------------------------------------------- internal */

    private function aksi(int $poId, string $metode, string $kata): void
    {
        $po = ProcurementService::find($poId);
        if ($po === null) {
            App::abort(404, 'Purchase order tidak ditemukan.');
        }

        try {
            ProcurementService::$metode($poId);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('procurement/' . $poId);
        }

        Audit::log($metode === 'setujui' ? 'approve' : 'submit', 'procurement.po', [
            'ref_table'   => 'purchase_orders',
            'ref_id'      => $poId,
            'ref_code'    => (string) $po['nomor'],
            'description' => ucfirst($kata) . ' PO ' . $po['nomor'],
        ]);
        Flash::success('Purchase order berhasil ' . $kata . '.');
        redirect('procurement/' . $poId);
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $items = (array) ($row['items'] ?? []);
        if ($items === []) {
            $items = [['item_id' => null, 'deskripsi' => '', 'coa_id' => null, 'qty' => 1, 'satuan' => 'unit', 'harga' => 0, 'ppn_persen' => 11]];
        }

        $this->page('pages/procurement/form', [
            'pageTitle'    => $id === null ? 'Purchase Order Baru' : 'Edit PO ' . (string) ($row['nomor'] ?? ''),
            'pageSubtitle' => 'PO menjadi komitmen anggaran dan dasar penerimaan barang.',
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Purchase Order', 'url' => url('procurement')],
                ['label' => $id === null ? 'PO Baru' : (string) ($row['nomor'] ?? ''), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url($id === null ? 'procurement' : 'procurement/' . $id)) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors,
            'items'        => array_values($items),
            'partners'     => Db::all("SELECT id, kode, nama, termin_hari FROM ms_partners WHERE tipe IN ('vendor','both') AND is_active = 1 ORDER BY nama"),
            'inventoryItems' => InventoryService::items('', '', 500),
            'projects'     => Db::all("SELECT id, kode, nama FROM ms_projects WHERE is_active = 1 ORDER BY kode DESC"),
            'businessUnits' => Db::all('SELECT id, nama FROM ms_business_units WHERE is_active = 1 ORDER BY kode'),
            'bidang'       => Db::all('SELECT id, nama FROM ms_bidang WHERE is_active = 1 ORDER BY kode'),
            'divisi'       => Db::all('SELECT id, nama FROM ms_divisi WHERE is_active = 1 ORDER BY kode'),
            'costCenters'  => Db::all('SELECT id, nama FROM ms_cost_centers WHERE is_active = 1 ORDER BY kode'),
            'coaBeban'     => Db::all("SELECT id, kode, nama FROM coa_accounts WHERE is_active = 1 AND allow_posting = 1 AND tipe IN ('beban','aset') ORDER BY kode"),
        ]);
    }
}
