<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Core\Validator;

/**
 * CRUD generik untuk master data organisasi (bidang, divisi, cost center,
 * unit usaha, vendor/customer, proyek, rekening bank).
 */
final class MasterController extends BaseController
{
    public function index(string $resource): void
    {
        $config = $this->resource($resource);
        $this->requireCan((string) ($config['view_permission'] ?? 'master.view'));

        $q = (string) input('q', '');
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        [$where, $params] = $this->searchClause($config, $q);

        $baseSql = (string) $config['list_sql'] . ' WHERE 1 = 1' . $where;
        $total = (int) Db::val('SELECT COUNT(*) FROM (' . $baseSql . ') AS x', $params);

        $paginator = new Paginator($total, $page, $perPage);
        $rows = Db::all(
            $baseSql . ' ORDER BY ' . $config['order'] . ' LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $this->page('pages/master/index', [
            'pageTitle'    => (string) $config['title'],
            'pageSubtitle' => (string) $config['description'],
            'breadcrumbs'  => [
                ['label' => 'Master Data', 'url' => null],
                ['label' => (string) $config['title'], 'url' => null],
            ],
            'config'       => $config,
            'resourceKey'  => $resource,
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'canManage'    => can((string) $config['permission']),
        ]);
    }

    public function create(string $resource): void
    {
        $config = $this->resource($resource);
        $this->requireCan((string) $config['permission']);

        $this->renderForm($config, $resource, null);
    }

    public function store(string $resource): void
    {
        $config = $this->resource($resource);
        $this->requireCan((string) $config['permission']);

        $fields = (array) $config['fields'];
        $rules = $this->rules($fields);
        $errors = Validator::make($_POST, $rules);

        if ($errors !== []) {
            error_bag($errors);
            keep_old($_POST);
            flash_error('Periksa kembali isian formulir.');
            redirect($config['route'] . '/create');
        }

        $data = $this->collectInput($fields);
        $data['created_at'] = now();

        $id = Db::insert((string) $config['table'], $data);

        Audit::log('create', (string) $config['audit'], [
            'ref_table'   => (string) $config['table'],
            'ref_id'      => $id,
            'ref_code'    => (string) ($data['kode'] ?? $data['nama'] ?? $id),
            'description' => 'Menambah ' . $config['singular'] . ': ' . ($data['nama'] ?? $data['kode'] ?? ''),
            'new'         => $data,
        ]);

        clear_old();
        flash_success($config['singular'] . ' berhasil ditambahkan.');
        redirect((string) $config['route']);
    }

    public function edit(string $resource, string $id): void
    {
        $config = $this->resource($resource);
        $this->requireCan((string) $config['permission']);

        $row = $this->findOrFail($config, (int) $id);
        $this->renderForm($config, $resource, $row);
    }

    public function update(string $resource, string $id): void
    {
        $config = $this->resource($resource);
        $this->requireCan((string) $config['permission']);

        $recordId = (int) $id;
        $old = $this->findOrFail($config, $recordId);

        $fields = (array) $config['fields'];
        $errors = Validator::make($_POST, $this->rules($fields), $recordId);

        if ($errors !== []) {
            error_bag($errors);
            keep_old($_POST);
            flash_error('Periksa kembali isian formulir.');
            redirect($config['route'] . '/' . $recordId . '/edit');
        }

        $data = $this->collectInput($fields);
        Db::update((string) $config['table'], $data, 'id = :id', [':id' => $recordId]);

        Audit::log('update', (string) $config['audit'], [
            'ref_table'   => (string) $config['table'],
            'ref_id'      => $recordId,
            'ref_code'    => (string) ($old['kode'] ?? $old['nama'] ?? $recordId),
            'description' => 'Mengubah ' . $config['singular'] . ': ' . ($old['nama'] ?? $old['kode'] ?? ''),
            'old'         => $old,
            'new'         => $data,
        ]);

        clear_old();
        flash_success($config['singular'] . ' berhasil diperbarui.');
        redirect((string) $config['route']);
    }

    public function toggle(string $resource, string $id): void
    {
        $config = $this->resource($resource);
        $this->requireCan((string) $config['permission']);

        $recordId = (int) $id;
        $old = $this->findOrFail($config, $recordId);
        $newStatus = ((int) $old['is_active'] === 1) ? 0 : 1;

        Db::exec('UPDATE `' . $config['table'] . '` SET is_active = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $recordId]);

        Audit::log('update', (string) $config['audit'], [
            'ref_table'   => (string) $config['table'],
            'ref_id'      => $recordId,
            'ref_code'    => (string) ($old['kode'] ?? $recordId),
            'description' => ($newStatus === 1 ? 'Mengaktifkan ' : 'Menonaktifkan ') . $config['singular'],
            'old'         => ['is_active' => (int) $old['is_active']],
            'new'         => ['is_active' => $newStatus],
        ]);

        flash_success($config['singular'] . ' berhasil ' . ($newStatus === 1 ? 'diaktifkan' : 'dinonaktifkan') . '.');
        back((string) $config['route']);
    }

    /* ------------------------------------------------------------- internal */

    /** @param array<string,mixed> $config @return array{0:string,1:array<int,mixed>} */
    private function searchClause(array $config, string $q): array
    {
        if ($q === '') {
            return ['', []];
        }
        $parts = [];
        $params = [];
        foreach ((array) $config['search'] as $column) {
            $parts[] = $column . ' LIKE ?';
            $params[] = '%' . $q . '%';
        }
        return [' AND (' . implode(' OR ', $parts) . ')', $params];
    }

    /** @param array<string,mixed> $config */
    private function findOrFail(array $config, int $id): array
    {
        $row = Db::one('SELECT * FROM `' . $config['table'] . '` WHERE id = ?', [$id]);
        if ($row === null) {
            App::abort(404, 'Data tidak ditemukan.');
        }
        return $row;
    }

    /** @param array<int,array<string,mixed>> $fields @return array<string,string> */
    private function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            if (!empty($field['rules'])) {
                $rules[(string) $field['name']] = (string) $field['rules'];
                continue;
            }
            $self = [];
            if (!empty($field['required'])) {
                $self[] = 'required';
            }
            if (!empty($field['max'])) {
                $self[] = 'max:' . (int) $field['max'];
            }
            if ($self !== []) {
                $rules[(string) $field['name']] = implode('|', $self);
            }
        }
        return $rules;
    }

    /** @param array<string,mixed> $config @param array<string,mixed>|null $row */
    private function renderForm(array $config, string $resource, ?array $row): void
    {
        $fields = (array) $config['fields'];
        $options = $this->fieldOptions($fields);
        $isEdit = $row !== null;

        $this->page('pages/master/form', [
            'pageTitle'    => ($isEdit ? 'Ubah ' : 'Tambah ') . $config['singular'],
            'pageSubtitle' => (string) $config['description'],
            'breadcrumbs'  => [
                ['label' => 'Master Data', 'url' => null],
                ['label' => (string) $config['title'], 'url' => url((string) $config['route'])],
                ['label' => $isEdit ? 'Ubah' : 'Tambah', 'url' => null],
            ],
            'config'      => $config,
            'resourceKey' => $resource,
            'fields'      => $fields,
            'options'     => $options,
            'row'         => $row,
            'isEdit'      => $isEdit,
            'errors'      => errors(),
        ]);
    }
}
