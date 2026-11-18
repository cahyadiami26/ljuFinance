<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;

/**
 * Pengguna aplikasi & penetapan role (hak akses) lokal.
 * Data pengguna berasal dari SSO; role dikelola di sini.
 */
final class UserController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('user.view');

        $q = (string) input('q', '');
        $roleFilter = (string) input('role', '');
        $page = max(1, (int) input('page', 1));

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.nik LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($roleFilter !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM app_user_roles ur JOIN app_roles r ON r.id = ur.role_id
                                 WHERE ur.user_id = u.id AND r.code = ?)';
            $params[] = $roleFilter;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM app_users u WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, (int) config('app.items_per_page', 15));

        $users = Db::all(
            "SELECT u.*,
                    (SELECT GROUP_CONCAT(r.name ORDER BY r.level SEPARATOR ', ')
                     FROM app_user_roles ur JOIN app_roles r ON r.id = ur.role_id
                     WHERE ur.user_id = u.id) AS role_names,
                    (SELECT GROUP_CONCAT(r2.code) FROM app_user_roles ur2 JOIN app_roles r2
                     WHERE ur2.user_id = u.id AND r2.id = ur2.role_id) AS role_codes,
                    d.nama AS divisi_nama, b.nama AS bidang_nama
             FROM app_users u
             LEFT JOIN ms_divisi d ON d.id = u.divisi_id
             LEFT JOIN ms_bidang b ON b.id = u.bidang_id
             WHERE {$whereSql}
             ORDER BY u.is_active DESC, u.name ASC
             LIMIT {$paginator->perPage} OFFSET {$paginator->offset()}",
            $params
        );

        $this->page('pages/user/index', [
            'pageTitle'    => 'Pengguna & Hak Akses',
            'pageSubtitle' => 'Data pengguna dari SSO beserta role dan kewenangan di aplikasi keuangan.',
            'breadcrumbs'  => [
                ['label' => 'System Admin', 'url' => null],
                ['label' => 'Pengguna & Hak Akses', 'url' => null],
            ],
            'users'      => $users,
            'roles'      => Db::all('SELECT * FROM app_roles ORDER BY level, name'),
            'paginator'  => $paginator,
            'q'          => $q,
            'roleFilter' => $roleFilter,
            'canManage'  => can('user.manage'),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('user.manage');

        $user = $this->findOrFail((int) $id);
        $assigned = array_map(
            static fn ($row) => (int) $row['role_id'],
            Db::all('SELECT role_id FROM app_user_roles WHERE user_id = ?', [(int) $id])
        );

        $this->page('pages/user/form', [
            'pageTitle'    => 'Hak Akses — ' . $user['name'],
            'pageSubtitle' => 'Tetapkan role yang menentukan kewenangan pengguna.',
            'breadcrumbs'  => [
                ['label' => 'System Admin', 'url' => null],
                ['label' => 'Pengguna & Hak Akses', 'url' => url('master/user')],
                ['label' => 'Hak Akses', 'url' => null],
            ],
            'user'     => $user,
            'roles'    => Db::all('SELECT r.*, (SELECT COUNT(*) FROM app_role_permissions rp WHERE rp.role_id = r.id) AS jumlah_permission
                                   FROM app_roles r ORDER BY r.level, r.name'),
            'assigned' => $assigned,
        ]);
    }

    public function updateRole(string $id): void
    {
        $this->requireCan('user.manage');

        $userId = (int) $id;
        $user = $this->findOrFail($userId);

        $roleIds = $_POST['roles'] ?? [];
        $roleIds = is_array($roleIds) ? array_values(array_unique(array_map('intval', $roleIds))) : [];

        $oldRoles = array_map(
            static fn ($row) => (int) $row['role_id'],
            Db::all('SELECT role_id FROM app_user_roles WHERE user_id = ?', [$userId])
        );

        Db::transaction(static function () use ($userId, $roleIds): void {
            Db::exec('DELETE FROM app_user_roles WHERE user_id = ?', [$userId]);
            foreach ($roleIds as $roleId) {
                if ($roleId > 0) {
                    Db::exec('INSERT IGNORE INTO app_user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)', [$userId, $roleId, auth_id()]);
                }
            }
        });

        Audit::log('update', 'user', [
            'ref_table'   => 'app_users',
            'ref_id'      => $userId,
            'ref_code'    => (string) $user['username'],
            'description' => 'Mengubah role pengguna ' . $user['name'],
            'old'         => ['roles' => $oldRoles],
            'new'         => ['roles' => $roleIds],
        ]);

        flash_success('Hak akses ' . $user['name'] . ' berhasil diperbarui.');
        redirect('master/user');
    }

    public function toggle(string $id): void
    {
        $this->requireCan('user.manage');

        $userId = (int) $id;
        $user = $this->findOrFail($userId);
        $newStatus = ((int) $user['is_active'] === 1) ? 0 : 1;

        Db::exec('UPDATE app_users SET is_active = ? WHERE id = ?', [$newStatus, $userId]);

        Audit::log('update', 'user', [
            'ref_table'   => 'app_users',
            'ref_id'      => $userId,
            'ref_code'    => (string) $user['username'],
            'description' => ($newStatus ? 'Mengaktifkan' : 'Menonaktifkan') . ' pengguna ' . $user['name'],
            'old'         => ['is_active' => (int) $user['is_active']],
            'new'         => ['is_active' => $newStatus],
        ]);

        flash_success('Status pengguna diperbarui.');
        back('master/user');
    }

    private function findOrFail(int $id): array
    {
        $user = Db::one('SELECT * FROM app_users WHERE id = ?', [$id]);
        if ($user === null) {
            App::abort(404, 'Pengguna tidak ditemukan.');
        }
        return $user;
    }
}
