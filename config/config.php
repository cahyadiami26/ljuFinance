<?php
/**
 * LJU Finance & Accounting System — Konfigurasi aplikasi
 * -------------------------------------------------------------------------
 * JANGAN menyimpan kredensial produksi di file ini bila repo bersifat publik.
 * Buat `config/config.local.php` yang hanya berisi nilai yang ingin dioverride
 * (file tersebut diabaikan oleh git). Contoh isi config.local.php:
 *
 *   <?php
 *   return [
 *       'db'  => ['user' => 'lju_fin', 'pass' => 'rahasia', 'name' => 'lju_finance'],
 *       'app' => ['env' => 'production', 'debug' => false],
 *   ];
 */

$config = [
    'app' => [
        'name'      => 'LJU Finance',
        'subtitle'  => 'Finance & Accounting System',
        'company'   => 'PT. Lampung Jasa Utama',
        'version'   => '1.0.0',
        'env'       => 'production',        // local | production
        'debug'     => false,
        'timezone'  => 'Asia/Jakarta',
        'locale'    => 'id',
        // true hanya bila mod_rewrite aktif dan .htaccess terpasang
        'pretty_url' => false,
        // kosongkan untuk deteksi otomatis
        'base_url'   => '',
        'items_per_page' => 15,
    ],

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'lju_finance',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    /*
     * SSO: tabel `users` berada pada database lain di mesin MySQL yang sama.
     * Login & sesi dipegang portal https://lju.co.id
     */
    'sso' => [
        'enabled'        => true,           // false = aplikasi dapat diakses tanpa portal (hanya dev)
        'db'             => 'lju_portal',   // nama database SSO
        'users_table'    => 'users',
        'session_name'   => 'LJU_APPS',
        'cookie_domain'  => '.lju.co.id',
        'cookie_lifetime' => 86400,
        'portal_url'     => 'https://lju.co.id/dashboard/',
        'target_system'  => 'https://finance.lju.co.id',
        'login_path'     => 'login',        // rute halaman pemilih user (hanya APP_ENV=local)
        'dev_login'      => false,          // WAJIB false di produksi
    ],

    'assets' => [
        'logo_url'       => 'https://dms.lju.co.id/assets/img/logo.png',
        'avatar_base_url'=> 'https://dms.lju.co.id/uploads/avatars/',
    ],

    'upload' => [
        'path'          => 'uploads',
        'max_size'      => 5242880,         // 5 MB
        'allowed_ext'   => ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'doc', 'docx'],
    ],

    'pdf' => [
        'paper'       => 'A4',
        'orientation' => 'portrait',
    ],

    'security' => [
        // token untuk membuka /install.php — WAJIB diganti
        'install_token' => 'lju-install-2025',
        'session_fingerprint' => true,
        'audit_retention_days' => 1825,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return $config;
