<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\AssetService;
use Lju\Services\PeriodService;

/**
 * Aset tetap: kartu inventaris, perolehan, penyusutan, mutasi,
 * penjualan, dan penghapusan.
 */
final class AssetController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('asset.view');

        $q = trim((string) input('q', ''));
        $kategori = (int) input('kategori', 0);
        $status = (string) input('status', '');
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(a.kode LIKE ? OR a.nama LIKE ? OR a.lokasi LIKE ? OR a.no_invoice LIKE ?)';
            $params = array_merge($params, ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
        }
        if ($kategori > 0) {
            $where[] = 'a.category_id = ?';
            $params[] = $kategori;
        }
        if ($status !== '' && array_key_exists($status, AssetService::STATUS)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM fixed_assets a WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT a.*, c.nama AS kategori_nama, c.metode, u.name AS penanggung_jawab_nama,
                    j.nomor AS journal_nomor
             FROM fixed_assets a
             LEFT JOIN asset_categories c ON c.id = a.category_id
             LEFT JOIN app_users u ON u.id = a.penanggung_jawab_user_id
             LEFT JOIN journal_entries j ON j.id = a.journal_id
             WHERE ' . $whereSql . '
             ORDER BY a.kode DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $this->page('pages/asset/index', [
            'pageTitle'    => 'Aset Tetap',
            'pageSubtitle' => 'Kartu inventaris, nilai perolehan, akumulasi penyusutan, dan nilai buku.',
            'breadcrumbs'  => [
                ['label' => 'Aset Tetap', 'url' => null],
                ['label' => 'Daftar Aset', 'url' => null],
            ],
            'pageActions'  => (can('asset.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('asset/create')) . '">' . icon('plus', 15) . ' Aset Baru</a>'
                    : '')
                . '<a class="btn" href="' . e(url('asset/depreciation')) . '">' . icon('trending-down', 15) . ' Penyusutan</a>',
            'rows'        => $rows,
            'paginator'   => $paginator,
            'q'           => $q,
            'kategori'    => $kategori,
            'status'      => $status,
            'categories'  => AssetService::categories(),
            'ringkasan'   => AssetService::ringkasan(),
            'perKategori' => AssetService::perKategori(),
            'statusList'  => AssetService::STATUS,
            'metode'      => AssetService::METODE,
            'canManage'   => can('asset.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('asset.manage');

        $this->renderForm(null, [
            'kode'              => '',
            'nama'              => '',
            'category_id'       => null,
            'tanggal_perolehan' => today(),
            'harga_perolehan'   => 0,
            'nilai_residu'      => 0,
            'umur_bulan'        => 0,
            'lokasi'            => '',
            'penanggung_jawab_user_id' => null,
            'bidang_id'         => null,
            'divisi_id'         => null,
            'project_id'        => null,
            'no_invoice'        => '',
            'keterangan'        => '',
            'bank_account_id'   => null,
        ]);
    }

    public function store(): void
    {
        $this->requireCan('asset.manage');

        $data = AssetService::normalisasi($_POST);
        $errors = AssetService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = AssetService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = AssetService::find($id);
        Audit::log('create', 'asset', [
            'ref_table'   => 'fixed_assets',
            'ref_id'      => $id,
            'ref_code'    => (string) ($row['kode'] ?? ''),
            'description' => 'Buat aset tetap ' . ($row['kode'] ?? ''),
        ]);

        flash_success('Aset ' . ($row['kode'] ?? '') . ' berhasil dibuat sebagai draft.');
        redirect('asset/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('asset.view');

        $row = AssetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Aset tidak ditemukan.');
        }

        $status = (string) $row['status'];

        $this->page('pages/asset/show', [
            'pageTitle'    => 'Aset ' . $row['kode'],
            'pageSubtitle' => (string) $row['nama'],
            'breadcrumbs'  => [
                ['label' => 'Aset Tetap', 'url' => url('asset')],
                ['label' => (string) $row['kode'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('asset')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'riwayat'      => AssetService::riwayatPenyusutan((int) $id),
            'mutasi'       => AssetService::mutasi((int) $id),
            'banks'        => $this->opsiBank(),
            'metode'       => AssetService::METODE,
            'statusList'   => AssetService::STATUS,
            'mutasiList'   => AssetService::MUTASI,
            'canEdit'      => can('asset.manage') && in_array($status, ['draft', 'aktif', 'nonaktif'], true),
            'canActivate'  => can('asset.manage') && $status === 'draft',
            'canDeactivate' => can('asset.manage') && $status === 'aktif',
            'canMutate'    => can('asset.manage') && in_array($status, ['aktif', 'nonaktif'], true),
            'canSell'      => can('asset.manage') && $status === 'aktif',
            'canDispose'   => can('asset.manage') && in_array($status, ['aktif', 'nonaktif'], true),
            'canDelete'    => can('asset.manage') && $status === 'draft' && empty($row['journal_id']),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('asset.manage');

        $row = AssetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Aset tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'aktif', 'nonaktif'], true)) {
            flash_error('Aset berstatus ' . AssetService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('asset/' . (int) $id);
        }

        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('asset.manage');

        $row = AssetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Aset tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft', 'aktif', 'nonaktif'], true)) {
            flash_error('Aset berstatus ' . AssetService::STATUS[(string) $row['status']] . ' tidak dapat diubah.');
            redirect('asset/' . (int) $id);
        }

        $data = AssetService::normalisasi($_POST);
        $errors = AssetService::validasi($data, (int) $id);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            AssetService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'asset', [
            'ref_table'   => 'fixed_assets',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['kode'],
            'description' => 'Ubah aset ' . $row['kode'],
            'old'         => $row,
            'new'         => $data,
        ]);

        flash_success('Aset ' . $row['kode'] . ' berhasil diperbarui.');
        redirect('asset/' . (int) $id);
    }

    public function activate(string $id): void
    {
        $this->requireCan('asset.manage');

        try {
            $journalId = AssetService::aktifkan(
                (int) $id,
                (int) input('bank_account_id', 0) ?: null,
                (int) input('coa_kredit_id', 0) ?: null
            );
            flash_success('Aset diaktifkan dan jurnal perolehan #' . $journalId . ' dibuat.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/' . (int) $id);
    }

    public function deactivate(string $id): void
    {
        $this->requireCan('asset.manage');

        try {
            AssetService::nonaktifkan((int) $id);
            flash_success('Aset dinonaktifkan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/' . (int) $id);
    }

    public function location(string $id): void
    {
        $this->requireCan('asset.manage');

        try {
            AssetService::mutasiLokasi(
                (int) $id,
                (string) input('lokasi', ''),
                (string) input('tanggal', today()),
                (string) input('keterangan', '')
            );
            flash_success('Mutasi lokasi aset dicatat.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/' . (int) $id);
    }

    public function sell(string $id): void
    {
        $this->requireCan('asset.manage');

        try {
            AssetService::jual((int) $id, $_POST);
            flash_success('Penjualan aset dicatat beserta jurnalnya.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/' . (int) $id);
    }

    public function dispose(string $id): void
    {
        $this->requireCan('asset.manage');

        try {
            AssetService::hapuskan((int) $id, $_POST);
            flash_success('Penghapusan aset dicatat beserta jurnalnya.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('asset.manage');

        $row = AssetService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Aset tidak ditemukan.');
        }

        try {
            AssetService::hapus((int) $id);
            Audit::log('delete', 'asset', [
                'ref_table'   => 'fixed_assets',
                'ref_id'      => (int) $id,
                'ref_code'    => (string) $row['kode'],
                'description' => 'Hapus aset draft ' . $row['kode'],
                'old'         => $row,
            ]);
            flash_success('Aset ' . $row['kode'] . ' dihapus.');
            redirect('asset');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('asset/' . (int) $id);
        }
    }

    public function depreciation(): void
    {
        $this->requireCan('asset.view');

        $periodeId = (int) input('periode', 0);
        if ($periodeId <= 0) {
            $periodeId = (int) (periode_aktif()['id'] ?? 0);
        }

        $periode = $periodeId > 0
            ? Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId])
            : null;

        $rencana = null;
        if ($periode !== null && (string) $periode['status'] === 'open') {
            try {
                $rencana = AssetService::rencanaPenyusutan((int) $periode['id']);
            } catch (\Throwable $e) {
                $rencana = null;
            }
        }

        $daftar = $periode !== null ? AssetService::daftarPenyusutan((int) $periode['id']) : [];
        $draft = 0;
        $posted = 0.0;
        foreach ($daftar as $item) {
            if ((string) $item['status'] === 'draft') {
                $draft++;
            } else {
                $posted += (float) $item['penyusutan'];
            }
        }

        $this->page('pages/asset/depreciation', [
            'pageTitle'    => 'Penyusutan Aset',
            'pageSubtitle' => 'Hitung penyusutan periode, simpan draft, lalu posting ke jurnal.',
            'breadcrumbs'  => [
                ['label' => 'Aset Tetap', 'url' => url('asset')],
                ['label' => 'Penyusutan', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('asset')) . '">' . icon('arrow-left', 15) . ' Daftar Aset</a>',
            'periode'      => $periode,
            'periodeList'  => $this->opsiPeriode(),
            'rencana'      => $rencana,
            'daftar'       => $daftar,
            'jumlahDraft'  => $draft,
            'totalPosted'  => $posted,
            'metode'       => AssetService::METODE,
            'canManage'    => can('asset.manage'),
        ]);
    }

    public function depreciationStore(): void
    {
        $this->requireCan('asset.manage');

        $periodeId = (int) input('periode_id', 0);

        try {
            $jumlah = AssetService::prosesPenyusutan($periodeId);
            if ($jumlah === 0) {
                flash_error('Tidak ada aset yang dapat disusutkan pada periode ini.');
            } else {
                flash_success($jumlah . ' baris penyusutan disimpan sebagai draft.');
            }
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/depreciation?periode=' . $periodeId);
    }

    public function depreciationPost(): void
    {
        $this->requireCan('asset.manage');

        $periodeId = (int) input('periode_id', 0);

        try {
            $journalId = AssetService::postingPenyusutan($periodeId);
            flash_success('Penyusutan diposting ke jurnal #' . $journalId . '.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/depreciation?periode=' . $periodeId);
    }

    public function depreciationClear(): void
    {
        $this->requireCan('asset.manage');

        $periodeId = (int) input('periode_id', 0);

        try {
            $jumlah = AssetService::hapusDraftPenyusutan($periodeId);
            flash_success($jumlah . ' baris draft penyusutan dihapus.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('asset/depreciation?periode=' . $periodeId);
    }

    /** @return array<int,array<string,mixed>> */
    private function opsiPeriode(): array
    {
        $tahun = PeriodService::years();
        $rows = [];
        foreach (array_slice($tahun, 0, 2) as $year) {
            foreach (PeriodService::forYear((int) $year) as $periode) {
                $rows[] = $periode;
            }
        }

        usort($rows, static fn ($a, $b) => [$b['tahun'], $b['bulan']] <=> [$a['tahun'], $a['bulan']]);
        return $rows;
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/asset/form', [
            'pageTitle'    => $id === null ? 'Aset Baru' : 'Ubah Aset',
            'pageSubtitle' => 'Data perolehan, umur ekonomis, lokasi, dan penanggung jawab aset.',
            'breadcrumbs'  => [
                ['label' => 'Aset Tetap', 'url' => url('asset')],
                ['label' => $id === null ? 'Baru' : (string) ($row['kode'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('asset') : url('asset/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors === [] ? errors() : $errors,
            'categories'   => AssetService::categories(),
            'users'        => $this->opsiUser(),
            'bidang'       => $this->opsiBidang(),
            'divisi'       => $this->opsiDivisi(),
            'projects'     => $this->opsiProject(),
            'banks'        => $this->opsiBank(),
            'metode'       => AssetService::METODE,
        ]);
    }
}
