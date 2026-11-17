<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Profil pengguna (read-only, bersumber dari SSO).
 */
final class ProfileController extends BaseController
{
    public function index(): void
    {
        $user = Db::one('SELECT * FROM app_users WHERE id = ?', [auth_id()]) ?? auth_user();

        $roles = Db::all(
            'SELECT r.* FROM app_user_roles ur JOIN app_roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.level',
            [auth_id()]
        );

        $permissions = Db::all(
            'SELECT DISTINCT rp.permission FROM app_user_roles ur
             JOIN app_role_permissions rp ON rp.role_id = ur.role_id
             WHERE ur.user_id = ? ORDER BY rp.permission',
            [auth_id()]
        );

        $recentActivity = Db::tableExists('audit_logs')
            ? Db::all('SELECT * FROM audit_logs WHERE user_id = ? ORDER BY id DESC LIMIT 10', [auth_id()])
            : [];

        $this->page('pages/profile/index', [
            'pageTitle'    => 'Profil Saya',
            'pageSubtitle' => 'Data kepegawaian dan kewenangan yang tersinkron dari portal SSO.',
            'breadcrumbs'  => [
                ['label' => 'Profil', 'url' => null],
            ],
            'profile'       => $user,
            'roles'         => $roles,
            'permissions'   => array_column($permissions, 'permission'),
            'recentActivity' => $recentActivity,
            'session'       => [
                'user_id'    => $_SESSION['user_id'] ?? null,
                'nik'        => $_SESSION['user_nik'] ?? null,
                'username'   => $_SESSION['username'] ?? null,
                'rfid'       => $_SESSION['rfid'] ?? null,
                'webmail'    => $_SESSION['webmail_user'] ?? null,
            ],
        ]);
    }

    public function sync(): void
    {
        Db::exec('UPDATE app_users SET synced_at = NULL WHERE id = ?', [auth_id()]);
        Audit::log('login', 'auth', [
            'ref_table'   => 'app_users',
            'ref_id'      => auth_id(),
            'description' => 'Sinkronisasi ulang data profil dari SSO',
        ]);
        flash_success('Data profil akan disinkronkan ulang dari portal SSO.');
        redirect('profile');
    }
}
