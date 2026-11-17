<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Auth;
use Lju\Core\Db;
use Lju\Core\View;

/**
 * Autentikasi.
 *
 * Di produksi halaman login TIDAK dipakai: sesi dibuat portal SSO lju.co.id.
 * Halaman pemilih user hanya tersedia bila APP_ENV=local & sso.dev_login=true.
 */
final class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if (!config('sso.dev_login') || config('app.env') !== 'local') {
            $portal = (string) config('sso.portal_url');
            $target = (string) config('sso.target_system');
            redirect_to($portal . '?target_system=' . urlencode($target) . '&redirect_after_login=' . urlencode(\Lju\Core\App::currentUrl()));
        }

        $users = Db::all(
            'SELECT u.*, (SELECT GROUP_CONCAT(r.name SEPARATOR ", ")
                          FROM app_user_roles ur JOIN app_roles r ON r.id = ur.role_id
                          WHERE ur.user_id = u.id) AS role_names
             FROM app_users u WHERE u.is_active = 1 ORDER BY u.id'
        );

        echo View::render('pages/login', [
            'users' => $users,
            'logo'  => logo_url(),
        ], 'layouts/blank');
    }

    public function login(): void
    {
        if (!config('sso.dev_login') || config('app.env') !== 'local') {
            flash_error('Login lokal tidak diaktifkan. Silakan masuk melalui portal SSO.');
            redirect('dashboard');
        }

        if (!\Lju\Core\Csrf::verify()) {
            flash_error('Token formulir tidak valid. Silakan coba lagi.');
            redirect('login');
        }

        $userId = (int) input('user_id', 0);
        if ($userId <= 0 || !Auth::loginAs($userId)) {
            flash_error('Pengguna tidak ditemukan atau tidak aktif.');
            redirect('login');
        }

        flash_success('Selamat datang, ' . Auth::name() . '.');
        redirect('dashboard');
    }

    public function logout(): void
    {
        $wasDev = !empty($_SESSION['_dev_login']);
        Auth::destroy();

        if ($wasDev || (config('sso.dev_login') && config('app.env') === 'local')) {
            redirect('login');
        }

        redirect_to((string) config('sso.portal_url'));
    }
}
