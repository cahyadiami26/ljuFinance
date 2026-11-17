<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Role & matrix permission.
 */
final class RoleController extends BaseController
{
    public const PERMISSION_GROUPS = [
        'Dashboard & Laporan' => [
            'dashboard.view' => 'Lihat dashboard',
            'report.view'    => 'Lihat laporan',
            'report.export'  => 'Ekspor laporan',
        ],
        'Master Data' => [
            'master.view'    => 'Lihat master data',
            'master.manage'  => 'Kelola master data',
            'coa.view'       => 'Lihat COA',
            'coa.manage'     => 'Kelola COA',
            'project.view'   => 'Lihat proyek',
            'project.manage' => 'Kelola proyek',
            'period.view'    => 'Lihat periode',
            'period.manage'  => 'Kelola / tutup periode',
        ],
        'Jurnal & Buku Besar' => [
            'journal.view'    => 'Lihat jurnal',
            'journal.create'  => 'Buat jurnal',
            'journal.submit'  => 'Ajukan jurnal',
            'journal.approve' => 'Setujui jurnal',
            'journal.post'    => 'Posting jurnal',
            'journal.reverse' => 'Jurnal pembalik',
        ],
        'Kas, Bank & Biaya' => [
            'cash.view'       => 'Lihat kas & bank',
            'cash.manage'     => 'Kelola kas & bank',
            'advance.view'    => 'Lihat uang muka',
            'advance.manage'  => 'Kelola uang muka',
            'advance.approve' => 'Setujui uang muka',
            'expense.view'    => 'Lihat biaya',
            'expense.manage'  => 'Kelola biaya',
            'expense.approve' => 'Setujui biaya',
        ],
        'Hutang & Piutang' => [
            'ap.view'    => 'Lihat hutang',
            'ap.manage'  => 'Kelola hutang',
            'ap.approve' => 'Setujui tagihan',
            'ap.pay'     => 'Bayar hutang',
            'ar.view'    => 'Lihat piutang',
            'ar.manage'  => 'Kelola piutang',
            'ar.approve' => 'Setujui invoice',
        ],
        'Anggaran' => [
            'budget.view'    => 'Lihat anggaran',
            'budget.manage'  => 'Susun anggaran',
            'budget.approve' => 'Setujui anggaran',
        ],
        'Aset & Pajak' => [
            'asset.view'   => 'Lihat aset',
            'asset.manage' => 'Kelola aset',
            'tax.view'     => 'Lihat pajak',
            'tax.manage'   => 'Kelola pajak',
        ],
        'Approval & Audit' => [
            'approval.view' => 'Lihat antrean approval',
            'approval.act'  => 'Beri keputusan approval',
            'audit.view'    => 'Lihat audit trail',
        ],
        'Administrasi Sistem' => [
            'user.view'        => 'Lihat pengguna',
            'user.manage'      => 'Kelola pengguna & role',
            'role.manage'      => 'Kelola role & permission',
            'settings.manage'  => 'Kelola pengaturan',
        ],
    ];

    public function index(): void
    {
        $this->requireCan('role.manage');

        $roles = Db::all('SELECT * FROM app_roles ORDER BY level, name');
        $permissions = [];
        foreach (Db::all('SELECT role_id, permission FROM app_role_permissions') as $row) {
            $permissions[(int) $row['role_id']][] = (string) $row['permission'];
        }

        $userCounts = [];
        foreach (Db::all('SELECT role_id, COUNT(*) AS jumlah FROM app_user_roles GROUP BY role_id') as $row) {
            $userCounts[(int) $row['role_id']] = (int) $row['jumlah'];
        }

        $this->page('pages/role/index', [
            'pageTitle'    => 'Role & Permission',
            'pageSubtitle' => 'Matriks kewenangan tiap role. Perubahan berlaku langsung setelah disimpan.',
            'breadcrumbs'  => [
                ['label' => 'System Admin', 'url' => null],
                ['label' => 'Role & Permission', 'url' => null],
            ],
            'roles'       => $roles,
            'permissions' => $permissions,
            'userCounts'  => $userCounts,
            'groups'      => self::PERMISSION_GROUPS,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireCan('role.manage');

        $roleId = (int) $id;
        $role = Db::one('SELECT * FROM app_roles WHERE id = ?', [$roleId]);
        if ($role === null) {
            flash_error('Role tidak ditemukan.');
            redirect('master/role');
        }

        $submitted = $_POST['permissions'] ?? [];
        $submitted = is_array($submitted) ? $submitted : [];

        $allowed = [];
        foreach (self::PERMISSION_GROUPS as $items) {
            $allowed = array_merge($allowed, array_keys($items));
        }
        $allowed = array_flip($allowed);

        $newPermissions = array_values(array_filter($submitted, static fn ($p) => isset($allowed[$p])));

        $old = array_map(
            static fn ($row) => (string) $row['permission'],
            Db::all('SELECT permission FROM app_role_permissions WHERE role_id = ?', [$roleId])
        );

        Db::transaction(static function () use ($roleId, $newPermissions): void {
            Db::exec('DELETE FROM app_role_permissions WHERE role_id = ?', [$roleId]);
            foreach ($newPermissions as $permission) {
                Db::exec('INSERT IGNORE INTO app_role_permissions (role_id, permission) VALUES (?, ?)', [$roleId, $permission]);
            }
        });

        Audit::log('update', 'role', [
            'ref_table'   => 'app_roles',
            'ref_id'      => $roleId,
            'ref_code'    => (string) $role['code'],
            'description' => 'Mengubah permission role ' . $role['name'],
            'old'         => $old,
            'new'         => $newPermissions,
        ]);

        flash_success('Permission role ' . $role['name'] . ' berhasil disimpan.');
        redirect('master/role');
    }
}
