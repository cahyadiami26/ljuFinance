<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Autentikasi berbasis SSO portal lju.co.id.
 *
 * Aplikasi tidak menyimpan password. Sesi dibuat oleh portal SSO, lalu aplikasi:
 *  1. memvalidasi $_SESSION['user_id'],
 *  2. menyinkronkan data user ke tabel lokal app_users,
 *  3. memuat role & permission lokal (RBAC).
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    /** @var array<int,string> */
    private static array $permissions = [];

    /** @var array<int,string> */
    private static array $roles = [];

    private static bool $booted = false;

    /* ------------------------------------------------------------------ sesi */

    public static function boot(): void
    {
        if (self::$booted || PHP_SAPI === 'cli') {
            return;
        }
        self::$booted = true;

        $domain = (string) Config::get('sso.cookie_domain', '.lju.co.id');
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        // cookie domain hanya dipakai bila host memang berada di bawah domain SSO
        $cookieDomain = ($domain !== '' && $host !== '' && str_ends_with($host, ltrim($domain, '.'))) ? $domain : '';

        ini_set('session.cookie_domain', $cookieDomain);
        session_name((string) Config::get('sso.session_name', 'LJU_APPS'));

        $params = [
            'lifetime' => (int) Config::get('sso.cookie_lifetime', 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ];
        if ($cookieDomain !== '') {
            $params['domain'] = $cookieDomain;
        }
        session_set_cookie_params($params);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /* --------------------------------------------------------------- penjaga */

    /**
     * Pastikan request terautentikasi. Bila belum, redirect ke portal SSO.
     */
    public static function guard(): void
    {
        if (!self::check()) {
            self::redirectToPortal();
        }

        try {
            self::loadUser();
        } catch (\Throwable $e) {
            self::destroy();
            throw $e;
        }

        if (!self::isActive()) {
            self::destroy();
            App::abort(403, 'Akun Anda tidak aktif. Hubungi administrator sistem.');
        }
    }

    private static function redirectToPortal(): void
    {
        if (Config::get('sso.dev_login') && Config::get('app.env') === 'local') {
            redirect('login');
        }

        if (!Config::get('sso.enabled', true)) {
            App::abort(401, 'Autentikasi SSO dinonaktifkan pada konfigurasi. Aktifkan sso.enabled atau gunakan mode pengembangan (app.env=local + sso.dev_login=true).');
        }

        $portal = (string) Config::get('sso.portal_url', 'https://lju.co.id/dashboard/');
        $target = (string) Config::get('sso.target_system', '');
        $current = App::currentUrl();

        $url = $portal . '?target_system=' . urlencode($target) . '&redirect_after_login=' . urlencode($current);
        header('Location: ' . $url, true, 302);
        exit;
    }

    /* ----------------------------------------------------------------- user */

    /** @return array<string,mixed> */
    public static function user(): array
    {
        if (self::$user === null) {
            self::loadUser();
        }
        return self::$user ?? [];
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function name(): string
    {
        return (string) (self::user()['name'] ?? 'Pengguna');
    }

    public static function isActive(): bool
    {
        return (int) (self::user()['is_active'] ?? 0) === 1;
    }

    /** @return array<int,string> */
    public static function roles(): array
    {
        self::user();
        return self::$roles;
    }

    /** @return array<int,string> */
    public static function permissions(): array
    {
        self::user();
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        if ($permission === '') {
            return true;
        }
        self::user();
        if (in_array('super_admin', self::$roles, true)) {
            return true;
        }
        return in_array($permission, self::$permissions, true);
    }

    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }

    public static function hasRole(string $roleCode): bool
    {
        return in_array($roleCode, self::roles(), true);
    }

    /** URL avatar (external) atau null bila memakai inisial. */
    public static function avatarUrl(?array $user = null): ?string
    {
        $user ??= self::user();
        $file = trim((string) ($user['avatar'] ?? ''));
        if ($file !== '') {
            return rtrim((string) Config::get('assets.avatar_base_url', ''), '/') . '/' . rawurlencode($file);
        }
        $photo = trim((string) ($user['photo'] ?? ''));
        if ($photo !== '') {
            return rtrim((string) Config::get('assets.avatar_base_url', ''), '/') . '/' . rawurlencode($photo);
        }
        return null;
    }

    /* --------------------------------------------------------------- sinkron */

    private static function loadUser(): void
    {
        if (self::$user !== null) {
            return;
        }

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $user = Db::one('SELECT * FROM `app_users` WHERE `id` = ?', [$id]);
        $stale = $user === null || strtotime((string) ($user['synced_at'] ?? '1970-01-01')) < time() - 300;

        if ($stale) {
            $user = self::syncUser($id) ?? $user;
        }

        if ($user === null) {
            // Database lokal belum diisi: pakai data sesi SSO apa adanya.
            $user = [
                'id'          => $id,
                'name'        => (string) ($_SESSION['user_name'] ?? 'Pengguna'),
                'username'    => (string) ($_SESSION['username'] ?? ''),
                'email'       => (string) ($_SESSION['user_email'] ?? ''),
                'position'    => (string) ($_SESSION['position'] ?? ''),
                'nama_bidang' => (string) ($_SESSION['division'] ?? ''),
                'nama_divisi' => (string) ($_SESSION['department'] ?? ''),
                'avatar'      => (string) ($_SESSION['avatar'] ?? ''),
                'photo'       => (string) ($_SESSION['photo'] ?? ''),
                'is_active'   => 1,
            ];
        }

        self::$user = $user;
        self::loadRolesAndPermissions($id);
    }

    private static function loadRolesAndPermissions(int $userId): void
    {
        self::$roles = [];
        self::$permissions = [];

        if (!Db::tableExists('app_user_roles')) {
            return;
        }

        foreach (Db::all(
            'SELECT r.code FROM app_user_roles ur JOIN app_roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$userId]
        ) as $row) {
            self::$roles[] = (string) $row['code'];
        }

        foreach (Db::all(
            'SELECT DISTINCT rp.permission FROM app_user_roles ur
             JOIN app_role_permissions rp ON rp.role_id = ur.role_id
             WHERE ur.user_id = ?',
            [$userId]
        ) as $row) {
            self::$permissions[] = (string) $row['permission'];
        }
    }

    /**
     * Ambil data terbaru dari tabel users milik SSO lalu simpan ke app_users.
     */
    private static function syncUser(int $id): ?array
    {
        $pdo = Db::sso();
        $row = null;

        if ($pdo !== null) {
            $table = (string) Config::get('sso.users_table', 'users');
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, nik, name, username, email, position, division, department, role, avatar, photo, signature, is_active
                     FROM `{$table}` WHERE id = ? LIMIT 1"
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            } catch (\PDOException) {
                $row = null;
            }
        }

        if ($row === null) {
            // fallback: data dari sesi SSO
            if (empty($_SESSION['user_name'])) {
                return null;
            }
            $row = [
                'id'         => $id,
                'nik'        => $_SESSION['user_nik'] ?? null,
                'name'       => $_SESSION['user_name'] ?? '',
                'username'   => $_SESSION['username'] ?? '',
                'email'      => $_SESSION['user_email'] ?? null,
                'position'   => $_SESSION['position'] ?? null,
                'division'   => $_SESSION['division'] ?? null,
                'department' => $_SESSION['department'] ?? null,
                'role'       => $_SESSION['role'] ?? 'user',
                'avatar'     => $_SESSION['avatar'] ?? null,
                'photo'      => $_SESSION['photo'] ?? null,
                'signature'  => $_SESSION['signature'] ?? null,
                'is_active'  => 1,
            ];
        }

        $data = [
            'id'          => (int) $row['id'],
            'nik'         => $row['nik'] ?: null,
            'name'        => (string) $row['name'],
            'username'    => (string) ($row['username'] ?: ('user' . $row['id'])),
            'email'       => $row['email'] ?: null,
            'position'    => $row['position'] ?: null,
            'nama_bidang' => $row['division'] ?: null,
            'nama_divisi' => $row['department'] ?: null,
            'sso_role'    => $row['role'] ?: null,
            'avatar'      => $row['avatar'] ?: null,
            'photo'       => $row['photo'] ?: null,
            'signature'   => $row['signature'] ?: null,
            'is_active'   => (int) ($row['is_active'] ?? 1),
        ];

        Db::exec(
            'INSERT INTO app_users (id, nik, name, username, email, position, nama_bidang, nama_divisi, sso_role,
                                    avatar, photo, signature, is_active, last_login_at, synced_at)
             VALUES (:id, :nik, :name, :username, :email, :position, :nama_bidang, :nama_divisi, :sso_role,
                     :avatar, :photo, :signature, :is_active, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                nik = VALUES(nik), name = VALUES(name), email = VALUES(email), position = VALUES(position),
                nama_bidang = VALUES(nama_bidang), nama_divisi = VALUES(nama_divisi), sso_role = VALUES(sso_role),
                avatar = VALUES(avatar), photo = VALUES(photo), signature = VALUES(signature),
                is_active = VALUES(is_active), last_login_at = NOW(), synced_at = NOW()',
            $data
        );

        // lengkapi relasi organisasi berdasarkan nama bidang/divisi SSO
        Db::exec(
            'UPDATE app_users u
             JOIN ms_bidang b ON b.nama_sso = u.nama_bidang
             SET u.bidang_id = b.id
             WHERE u.id = ? AND u.bidang_id IS NULL',
            [$data['id']]
        );
        Db::exec(
            'UPDATE app_users u
             JOIN ms_divisi d ON d.nama_sso = u.nama_divisi
             SET u.divisi_id = d.id
             WHERE u.id = ? AND u.divisi_id IS NULL',
            [$data['id']]
        );

        return Db::one('SELECT * FROM `app_users` WHERE `id` = ?', [$data['id']]);
    }

    /* ----------------------------------------------------------------- keluar */

    public static function destroy(): void
    {
        self::$user = null;
        self::$permissions = [];
        self::$roles = [];

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Login lokal untuk pengembangan (APP_ENV=local, sso.dev_login=true).
     * Meniru apa yang dilakukan portal SSO terhadap session.
     */
    public static function loginAs(int $userId): bool
    {
        $user = Db::one('SELECT * FROM `app_users` WHERE `id` = ? AND `is_active` = 1', [$userId]);
        if ($user === null) {
            return false;
        }

        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['user_nik']   = $user['nik'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['sso_role'];
        $_SESSION['position']   = $user['position'];
        $_SESSION['division']   = $user['nama_bidang'];
        $_SESSION['department'] = $user['nama_divisi'];
        $_SESSION['avatar']     = $user['avatar'];
        $_SESSION['photo']      = $user['photo'];
        $_SESSION['signature']  = $user['signature'];
        $_SESSION['_dev_login'] = true;

        Db::exec('UPDATE app_users SET last_login_at = NOW(), synced_at = NOW() WHERE id = ?', [$userId]);
        self::$user = null;

        Audit::log('login', 'auth', [
            'ref_table'   => 'app_users',
            'ref_id'      => (int) $user['id'],
            'ref_code'    => (string) $user['username'],
            'description' => 'Login lokal (mode pengembangan)',
        ]);

        return true;
    }
}
