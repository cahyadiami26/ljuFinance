<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Flash;
use Lju\Services\InventoryService;
use Lju\Services\JournalService;

/**
 * Persediaan: master barang, gudang, kartu stok, serta mutasi masuk/keluar.
 */
final class InventoryController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('inventory.view');

        $q = trim((string) input('q', ''));
        $kategori = trim((string) input('kategori', ''));

        $items = InventoryService::items($q, $kategori);
        $nilai = 0.0;
        foreach ($items as $item) {
            $nilai += (float) $item['nilai_stok'];
        }

        $this->page('pages/inventory/index', [
            'pageTitle'    => 'Persediaan',
            'pageSubtitle' => 'Master barang, nilai stok rata-rata bergerak, dan peringatan stok minimum.',
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Persediaan', 'url' => null],
            ],
            'pageActions'  => (can('inventory.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('inventory/item/create')) . '">' . icon('plus', 15) . ' Barang Baru</a>'
                    : '')
                . '<a class="btn" href="' . e(url('inventory/movements')) . '">' . icon('history', 15) . ' Kartu Stok</a>',
            'items'        => $items,
            'q'            => $q,
            'kategori'     => $kategori,
            'kategoriList' => InventoryService::kategori(),
            'ringkasan'    => InventoryService::ringkasan(),
            'nilaiTampil'  => $nilai,
            'warehouses'   => InventoryService::warehouses(false),
            'gerakTerakhir' => InventoryService::movements(0, 8),
            'canManage'    => can('inventory.manage'),
        ]);
    }

    public function movements(): void
    {
        $this->requireCan('inventory.view');

        $itemId = (int) input('item', 0);
        $tipe = trim((string) input('tipe', ''));

        $this->page('pages/inventory/movements', [
            'pageTitle'    => 'Kartu Stok',
            'pageSubtitle' => 'Riwayat mutasi persediaan masuk, keluar, dan penyesuaian.',
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Persediaan', 'url' => url('inventory')],
                ['label' => 'Kartu Stok', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('inventory')) . '">' . icon('arrow-left', 15) . ' Daftar Barang</a>',
            'movements'    => InventoryService::movements($itemId, 200, $tipe),
            'items'        => InventoryService::items('', '', 500),
            'itemId'       => $itemId,
            'tipe'         => $tipe,
            'tipeList'     => InventoryService::TIPE,
        ]);
    }

    public function show(string $id): void
    {
        $this->requireCan('inventory.view');

        $itemId = (int) $id;
        $item = InventoryService::item($itemId);
        if ($item === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $masuk = (float) Db::val("SELECT COALESCE(SUM(qty), 0) FROM inventory_movements WHERE item_id = ? AND tipe = 'masuk'", [$itemId]);
        $keluar = (float) Db::val("SELECT COALESCE(SUM(qty), 0) FROM inventory_movements WHERE item_id = ? AND tipe = 'keluar'", [$itemId]);

        $this->page('pages/inventory/show', [
            'pageTitle'    => $item['kode'] . ' — ' . $item['nama'],
            'pageSubtitle' => 'Kartu stok, nilai rata-rata, dan riwayat mutasi barang.',
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Persediaan', 'url' => url('inventory')],
                ['label' => (string) $item['kode'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('inventory')) . '">' . icon('arrow-left', 15) . ' Daftar</a>'
                . (can('inventory.manage')
                    ? '<a class="btn" href="' . e(url('inventory/item/' . $itemId . '/edit')) . '">' . icon('edit', 15) . ' Edit Barang</a>'
                    . '<a class="btn" href="' . e(url('inventory/item/' . $itemId . '/masuk')) . '">' . icon('arrow-down', 15) . ' Barang Masuk</a>'
                    . '<a class="btn btn--primary" href="' . e(url('inventory/item/' . $itemId . '/keluar')) . '">' . icon('arrow-up', 15) . ' Barang Keluar</a>'
                    : ''),
            'item'         => $item,
            'movements'    => InventoryService::movements($itemId, 200),
            'masuk'        => $masuk,
            'keluar'       => $keluar,
            'nilaiStok'    => InventoryService::nilaiStok($itemId),
            'canManage'    => can('inventory.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('inventory.manage');

        $this->renderForm(null, [
            'kode'              => '',
            'nama'              => '',
            'kategori'          => 'material',
            'satuan'            => 'unit',
            'coa_persediaan_id' => null,
            'coa_beban_id'      => null,
            'stok_min'          => 0,
            'is_active'         => 1,
        ]);
    }

    public function store(): void
    {
        $this->requireCan('inventory.manage');

        $data = InventoryService::normalisasiItem($_POST);
        $errors = InventoryService::validasiItem($data);

        if ($errors !== []) {
            $this->renderForm(null, $data, $errors);
            return;
        }

        $id = InventoryService::simpanItem(null, $data);
        Audit::log('create', 'inventory.item', [
            'ref_table'   => 'inventory_items',
            'ref_id'      => $id,
            'ref_code'    => (string) $data['kode'],
            'description' => 'Menambah barang persediaan ' . $data['kode'] . ' — ' . $data['nama'],
            'new'         => $data,
        ]);
        Flash::success('Barang ' . $data['kode'] . ' berhasil dibuat.');
        redirect('inventory/item/' . $id);
    }

    public function edit(string $id): void
    {
        $this->requireCan('inventory.manage');

        $itemId = (int) $id;
        $row = InventoryService::item($itemId);
        if ($row === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $this->renderForm($itemId, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('inventory.manage');

        $itemId = (int) $id;
        if (InventoryService::item($itemId) === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $data = InventoryService::normalisasiItem($_POST);
        $errors = InventoryService::validasiItem($data, $itemId);

        if ($errors !== []) {
            $this->renderForm($itemId, $data, $errors);
            return;
        }

        InventoryService::simpanItem($itemId, $data);
        Audit::log('update', 'inventory.item', [
            'ref_table'   => 'inventory_items',
            'ref_id'      => $itemId,
            'ref_code'    => (string) $data['kode'],
            'description' => 'Memperbarui barang persediaan ' . $data['kode'],
            'new'         => $data,
        ]);
        Flash::success('Barang ' . $data['kode'] . ' berhasil diperbarui.');
        redirect('inventory/item/' . $itemId);
    }

    public function masukForm(string $id): void
    {
        $this->requireCan('inventory.manage');

        $item = InventoryService::item((int) $id);
        if ($item === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $this->page('pages/inventory/masuk', [
            'pageTitle'    => 'Barang Masuk — ' . $item['nama'],
            'pageSubtitle' => 'Penerimaan barang menambah stok dan memperbarui harga rata-rata.',
            'breadcrumbs'  => [
                ['label' => 'Persediaan', 'url' => url('inventory')],
                ['label' => (string) $item['kode'], 'url' => url('inventory/item/' . (int) $item['id'])],
                ['label' => 'Barang Masuk', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('inventory/item/' . (int) $item['id'])) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'item'         => $item,
            'warehouses'   => InventoryService::warehouses(),
            'defaults'     => ['tanggal' => today(), 'qty' => 1, 'harga' => (float) ($item['harga_rata'] ?? 0), 'warehouse_id' => null, 'keterangan' => ''],
        ]);
    }

    public function masukStore(string $id): void
    {
        $this->requireCan('inventory.manage');

        $itemId = (int) $id;
        $item = InventoryService::item($itemId);
        if ($item === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $tanggal = trim((string) input('tanggal', today())) ?: today();
        $qty = (float) JournalService::uang(input('qty', 0));
        $harga = (float) JournalService::uang(input('harga', 0));
        $warehouseId = (int) input('warehouse_id', 0) ?: null;
        $keterangan = trim((string) input('keterangan', ''));

        if ($qty <= 0) {
            Flash::error('Qty penerimaan harus lebih besar dari nol.');
            redirect('inventory/item/' . $itemId . '/masuk');
        }
        if ($harga < 0) {
            Flash::error('Harga satuan tidak boleh negatif.');
            redirect('inventory/item/' . $itemId . '/masuk');
        }

        try {
            $movementId = InventoryService::catatMasuk($itemId, $qty, $harga, $tanggal, $warehouseId, 'manual', null, null, $keterangan);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('inventory/item/' . $itemId . '/masuk');
        }

        Audit::log('create', 'inventory.movement', [
            'ref_table'   => 'inventory_movements',
            'ref_id'      => $movementId,
            'ref_code'    => (string) $item['kode'],
            'description' => 'Penerimaan barang ' . $item['kode'] . ' sebanyak ' . $qty . ' ' . $item['satuan'],
            'new'         => ['qty' => $qty, 'harga' => $harga, 'tanggal' => $tanggal],
        ]);
        Flash::success('Penerimaan barang dicatat. Stok dan harga rata-rata diperbarui.');
        redirect('inventory/item/' . $itemId);
    }

    public function keluarForm(string $id): void
    {
        $this->requireCan('inventory.manage');

        $item = InventoryService::item((int) $id);
        if ($item === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $this->page('pages/inventory/keluar', [
            'pageTitle'    => 'Barang Keluar — ' . $item['nama'],
            'pageSubtitle' => 'Pengeluaran barang membentuk jurnal Dr beban / Cr persediaan.',
            'breadcrumbs'  => [
                ['label' => 'Persediaan', 'url' => url('inventory')],
                ['label' => (string) $item['kode'], 'url' => url('inventory/item/' . (int) $item['id'])],
                ['label' => 'Barang Keluar', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('inventory/item/' . (int) $item['id'])) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'item'         => $item,
            'warehouses'   => InventoryService::warehouses(),
            'projects'     => Db::all("SELECT id, kode, nama FROM ms_projects WHERE status = 'berjalan' AND is_active = 1 ORDER BY kode DESC"),
            'businessUnits' => Db::all('SELECT id, nama FROM ms_business_units WHERE is_active = 1 ORDER BY kode'),
            'bidang'       => Db::all('SELECT id, nama FROM ms_bidang WHERE is_active = 1 ORDER BY kode'),
            'divisi'       => Db::all('SELECT id, nama FROM ms_divisi WHERE is_active = 1 ORDER BY kode'),
            'defaults'     => ['tanggal' => today(), 'qty' => 1, 'warehouse_id' => null, 'project_id' => null, 'business_unit_id' => null, 'bidang_id' => null, 'divisi_id' => null, 'keterangan' => ''],
            'nilaiStok'    => InventoryService::nilaiStok((int) $item['id']),
            'bebanKode'    => (string) ($item['coa_beban_kode'] ?? ''),
        ]);
    }

    public function keluarStore(string $id): void
    {
        $this->requireCan('inventory.manage');

        $itemId = (int) $id;
        $item = InventoryService::item($itemId);
        if ($item === null) {
            App::abort(404, 'Barang persediaan tidak ditemukan.');
        }

        $tanggal = trim((string) input('tanggal', today())) ?: today();
        $qty = (float) JournalService::uang(input('qty', 0));
        $keterangan = trim((string) input('keterangan', ''));

        if ($qty <= 0) {
            Flash::error('Qty pengeluaran harus lebih besar dari nol.');
            redirect('inventory/item/' . $itemId . '/keluar');
        }

        try {
            $hasil = InventoryService::catatKeluar(
                $itemId,
                $qty,
                $tanggal,
                (int) input('warehouse_id', 0) ?: null,
                (int) input('project_id', 0) ?: null,
                (int) input('business_unit_id', 0) ?: null,
                (int) input('bidang_id', 0) ?: null,
                (int) input('divisi_id', 0) ?: null,
                $keterangan
            );
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('inventory/item/' . $itemId . '/keluar');
        }

        Audit::log('create', 'inventory.movement', [
            'ref_table'   => 'inventory_movements',
            'ref_id'      => $hasil['movement'],
            'ref_code'    => (string) $item['kode'],
            'description' => 'Pengeluaran barang ' . $item['kode'] . ' sebanyak ' . $qty . ' ' . $item['satuan'],
            'new'         => ['qty' => $qty, 'journal_id' => $hasil['journal'], 'tanggal' => $tanggal],
        ]);
        Flash::success('Pengeluaran barang dicatat dan jurnal persediaan terbentuk.');
        redirect('inventory/item/' . $itemId);
    }

    /* --------------------------------------------------------- internal */

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/inventory/form', [
            'pageTitle'    => $id === null ? 'Barang Persediaan Baru' : 'Edit Barang Persediaan',
            'pageSubtitle' => 'Akun persediaan dan akun beban menentukan jurnal mutasi barang.',
            'breadcrumbs'  => [
                ['label' => 'Pengadaan & Persediaan', 'url' => null],
                ['label' => 'Persediaan', 'url' => url('inventory')],
                ['label' => $id === null ? 'Barang Baru' : 'Edit Barang', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url($id === null ? 'inventory' : 'inventory/item/' . $id)) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors,
            'kategoriList' => InventoryService::kategori(),
            'coaPersediaan' => Db::all("SELECT id, kode, nama FROM coa_accounts WHERE is_active = 1 AND tipe = 'aset' AND (kategori = 'aset_lancar' OR kode LIKE '1-15%') ORDER BY kode"),
            'coaBeban'     => Db::all("SELECT id, kode, nama FROM coa_accounts WHERE is_active = 1 AND tipe = 'beban' ORDER BY kode"),
        ]);
    }
}
