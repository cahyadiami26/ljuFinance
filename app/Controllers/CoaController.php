<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Core\Validator;

/**
 * Chart of Accounts: struktur akun berjenjang (parent/sub-akun).
 */
final class CoaController extends BaseController
{
    private const TIPE = [
        'aset'       => 'Aset',
        'kewajiban'  => 'Kewajiban',
        'ekuitas'    => 'Ekuitas',
        'pendapatan' => 'Pendapatan',
        'beban'      => 'Beban',
    ];

    private const KATEGORI = [
        'aset_lancar'        => 'Aset Lancar',
        'aset_tetap'         => 'Aset Tetap',
        'aset_lain'          => 'Aset Lain-lain',
        'kewajiban_lancar'   => 'Kewajiban Jangka Pendek',
        'kewajiban_panjang'  => 'Kewajiban Jangka Panjang',
        'ekuitas'            => 'Ekuitas',
        'pendapatan_usaha'   => 'Pendapatan Usaha',
        'pendapatan_lain'    => 'Pendapatan Lain-lain',
        'harga_pokok'        => 'Harga Pokok / Beban Pokok',
        'beban_operasional'  => 'Beban Operasional',
        'beban_lain'         => 'Beban Lain-lain',
        'pajak'              => 'Perpajakan',
    ];

    public function index(): void
    {
        $this->requireCan('coa.view');

        $q = (string) input('q', '');
        $tipe = (string) input('tipe', '');
        $status = (string) input('status', '');
        $page = max(1, (int) input('page', 1));

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(kode LIKE ? OR nama LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($tipe !== '' && isset(self::TIPE[$tipe])) {
            $where[] = 'tipe = ?';
            $params[] = $tipe;
        }
        if ($status === 'aktif') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'nonaktif') {
            $where[] = 'is_active = 0';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM coa_accounts WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, 50);

        $rows = Db::all(
            'SELECT * FROM coa_accounts WHERE ' . $whereSql . ' ORDER BY kode ASC LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $summary = Db::all(
            "SELECT tipe, COUNT(*) AS jumlah, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS aktif
             FROM coa_accounts GROUP BY tipe"
        );

        $this->page('pages/coa/index', [
            'pageTitle'    => 'Chart of Accounts',
            'pageSubtitle' => 'Struktur akun berjenjang sebagai fondasi seluruh pencatatan transaksi.',
            'breadcrumbs'  => [
                ['label' => 'Master Data', 'url' => null],
                ['label' => 'Chart of Accounts', 'url' => null],
            ],
            'rows'      => $rows,
            'summary'   => $summary,
            'tipeList'  => self::TIPE,
            'paginator' => $paginator,
            'q'         => $q,
            'tipe'      => $tipe,
            'status'    => $status,
            'canManage' => can('coa.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('coa.manage');
        $this->renderForm(null);
    }

    public function store(): void
    {
        $this->requireCan('coa.manage');
        $errors = Validator::make($_POST, $this->rules());

        if ($errors !== []) {
            error_bag($errors);
            keep_old($_POST);
            flash_error('Periksa kembali isian formulir.');
            redirect('master/coa/create');
        }

        $data = $this->payload(null);
        $id = Db::insert('coa_accounts', $data);

        Audit::log('create', 'coa', [
            'ref_table'   => 'coa_accounts',
            'ref_id'      => $id,
            'ref_code'    => (string) $data['kode'],
            'description' => 'Menambah akun ' . $data['kode'] . ' — ' . $data['nama'],
            'new'         => $data,
        ]);

        clear_old();
        flash_success('Akun ' . $data['kode'] . ' berhasil ditambahkan.');
        redirect('master/coa');
    }

    public function edit(string $id): void
    {
        $this->requireCan('coa.manage');
        $row = $this->find((int) $id);
        $this->renderForm($row);
    }

    public function update(string $id): void
    {
        $this->requireCan('coa.manage');
        $row = $this->find((int) $id);
        $errors = Validator::make($_POST, $this->rules((int) $id), (int) $id);

        if ($errors !== []) {
            error_bag($errors);
            keep_old($_POST);
            flash_error('Periksa kembali isian formulir.');
            redirect('master/coa/' . $id . '/edit');
        }

        $data = $this->payload($row);
        Db::update('coa_accounts', $data, 'id = :id', [':id' => (int) $id]);

        Audit::log('update', 'coa', [
            'ref_table'   => 'coa_accounts',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['kode'],
            'description' => 'Mengubah akun ' . $row['kode'] . ' — ' . $row['nama'],
            'old'         => $row,
            'new'         => $data,
        ]);

        clear_old();
        flash_success('Akun berhasil diperbarui.');
        redirect('master/coa');
    }

    public function toggle(string $id): void
    {
        $this->requireCan('coa.manage');
        $row = $this->find((int) $id);
        $newStatus = ((int) $row['is_active'] === 1) ? 0 : 1;

        Db::exec('UPDATE coa_accounts SET is_active = ? WHERE id = ?', [$newStatus, (int) $id]);

        Audit::log('update', 'coa', [
            'ref_table'   => 'coa_accounts',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $row['kode'],
            'description' => ($newStatus ? 'Mengaktifkan' : 'Menonaktifkan') . ' akun ' . $row['kode'],
            'old'         => ['is_active' => (int) $row['is_active']],
            'new'         => ['is_active' => $newStatus],
        ]);

        flash_success('Status akun diperbarui.');
        back('master/coa');
    }

    /* ------------------------------------------------------------- internal */

    private function find(int $id): array
    {
        $row = Db::one('SELECT * FROM coa_accounts WHERE id = ?', [$id]);
        if ($row === null) {
            App::abort(404, 'Akun tidak ditemukan.');
        }
        return $row;
    }

    /** @return array<string,string> */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'kode'         => 'required|max:20|unique:coa_accounts,kode',
            'nama'         => 'required|max:120',
            'tipe'         => 'required|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'kategori'     => 'required|in:' . implode(',', array_keys(self::KATEGORI)),
            'saldo_normal' => 'required|in:D,K',
            'level'        => 'nullable|integer',
            'parent_id'    => 'nullable|integer',
        ];
    }

    /** @return array<string,mixed> */
    private function payload(?array $row): array
    {
        $parentId = input('parent_id');
        $parentId = ($parentId === '' || $parentId === null) ? null : (int) $parentId;

        $level = 1;
        if ($parentId !== null) {
            $parentLevel = (int) Db::val('SELECT level FROM coa_accounts WHERE id = ?', [$parentId], 0);
            $level = $parentLevel + 1;
        }

        return [
            'kode'          => (string) input('kode'),
            'nama'          => (string) input('nama'),
            'tipe'          => (string) input('tipe', 'aset'),
            'kategori'      => (string) input('kategori', 'aset_lancar'),
            'parent_id'     => $parentId,
            'level'         => $level,
            'saldo_normal'  => (string) input('saldo_normal', 'D'),
            'allow_posting' => (int) (bool) input('allow_posting'),
            'is_kas_bank'   => (int) (bool) input('is_kas_bank'),
            'is_terkontrol' => (int) (bool) input('is_terkontrol'),
            'arus_kas'      => (string) input('arus_kas', 'operasi'),
            'deskripsi'     => (string) input('deskripsi') ?: null,
            'is_active'     => (int) (bool) input('is_active'),
        ];
    }

    /** @param array<string,mixed>|null $row */
    private function renderForm(?array $row): void
    {
        $parents = Db::all('SELECT id, kode, nama, level FROM coa_accounts ORDER BY kode ASC');
        $isEdit = $row !== null;

        $this->page('pages/coa/form', [
            'pageTitle'    => ($isEdit ? 'Ubah' : 'Tambah') . ' Akun COA',
            'pageSubtitle' => 'Kode akun, klasifikasi, akun induk, dan saldo normal.',
            'breadcrumbs'  => [
                ['label' => 'Master Data', 'url' => null],
                ['label' => 'Chart of Accounts', 'url' => url('master/coa')],
                ['label' => $isEdit ? 'Ubah' : 'Tambah', 'url' => null],
            ],
            'row'      => $row,
            'isEdit'   => $isEdit,
            'parents'  => $parents,
            'tipeList' => self::TIPE,
            'kategoriList' => self::KATEGORI,
            'errors'   => errors(),
        ]);
    }
}
