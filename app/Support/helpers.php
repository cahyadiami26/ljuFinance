<?php
/**
 * Helper global aplikasi.
 */

declare(strict_types=1);

use Lju\Core\Audit;
use Lju\Core\Auth;
use Lju\Core\Config;
use Lju\Core\Csrf;
use Lju\Core\Db;
use Lju\Core\Flash;

/* ------------------------------------------------------------------ config */

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/* ------------------------------------------------------------------- output */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '', array $params = []): string
{
    $base = \Lju\Core\App::baseUrl();

    if (config('app.pretty_url')) {
        $href = $base . '/' . ltrim($path, '/');
        if ($path === '') {
            $href = $base . '/';
        }
    } else {
        $href = $base . '/index.php' . ($path === '' ? '' : '?r=' . $path);
        if ($path === '') {
            $href = $base . '/index.php?r=dashboard';
        }
    }

    if ($params !== []) {
        $href .= (str_contains($href, '?') ? '&' : '?') . http_build_query($params);
    }

    return $href;
}

function asset(string $path): string
{
    return \Lju\Core\App::baseUrl() . '/assets/' . ltrim($path, '/');
}

function logo_url(): string
{
    return (string) setting('logo_url', config('assets.logo_url', ''));
}

function avatar_url(?array $user = null): ?string
{
    return Auth::avatarUrl($user);
}

function inisial(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return 'LJU';
    }
    $parts = explode(' ', $name);
    $first = mb_substr($parts[0], 0, 1);
    $second = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0], 1, 1);
    return mb_strtoupper($first . $second);
}

/* ---------------------------------------------------------------- navigasi */

function redirect(string $path = 'dashboard', array $params = []): never
{
    header('Location: ' . url($path, $params), true, 302);
    exit;
}

function redirect_to(string $absoluteUrl): never
{
    header('Location: ' . $absoluteUrl, true, 302);
    exit;
}

function back(string $fallback = 'dashboard'): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($referer !== '' && str_contains($referer, $host)) {
        header('Location: ' . $referer, true, 302);
        exit;
    }
    redirect($fallback);
}

function is_route(string $prefix): bool
{
    $path = \Lju\Core\Router::resolvePath();
    return $prefix === '' ? $path === '' : ($path === $prefix || str_starts_with($path, $prefix . '/'));
}

function route_path(): string
{
    return \Lju\Core\Router::resolvePath();
}

/* -------------------------------------------------------------------- auth */

function auth_user(): array
{
    return Auth::user();
}

function auth_id(): int
{
    return Auth::id();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function can_any(array $permissions): bool
{
    return Auth::canAny($permissions);
}

function has_role(string $role): bool
{
    return Auth::hasRole($role);
}

function csrf_field(): string
{
    return Csrf::field();
}

/* ------------------------------------------------------------------- input */

function input(string $key, mixed $default = null): mixed
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_string($value)) {
        $value = trim($value);
    }
    return $value;
}

function old(string $key, mixed $default = null): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/** @return array<string,string> */
function errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return is_array($errors) ? $errors : [];
}

function error_bag(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

function flash_success(string $message): void
{
    Flash::success($message);
}

function flash_error(string $message): void
{
    Flash::error($message);
}

function flash_messages(): array
{
    return Flash::pull();
}

/* ---------------------------------------------------------------- settings */

function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            foreach (Db::all('SELECT setting_key, setting_value FROM app_settings') as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {
            $settings = [];
        }
    }
    $value = $settings[$key] ?? null;
    return ($value === null || $value === '') ? $default : $value;
}

/* ----------------------------------------------------------------- format */

function rupiah(mixed $value, int $decimals = 0): string
{
    $number = (float) $value;
    $sign = $number < 0 ? '-' : '';
    return $sign . 'Rp ' . number_format(abs($number), $decimals, ',', '.');
}

function rupiah_short(mixed $value): string
{
    $number = (float) $value;
    $abs = abs($number);
    $sign = $number < 0 ? '-' : '';

    if ($abs >= 1_000_000_000_000) {
        return $sign . 'Rp ' . number_format($abs / 1_000_000_000_000, 2, ',', '.') . ' T';
    }
    if ($abs >= 1_000_000_000) {
        return $sign . 'Rp ' . number_format($abs / 1_000_000_000, 2, ',', '.') . ' M';
    }
    if ($abs >= 1_000_000) {
        return $sign . 'Rp ' . number_format($abs / 1_000_000, 2, ',', '.') . ' jt';
    }
    return rupiah($number, 0);
}

function angka(mixed $value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals, ',', '.');
}

function persen(mixed $value, int $decimals = 1): string
{
    return number_format((float) $value, $decimals, ',', '.') . '%';
}

function bulan_nama(int $bulan): string
{
    $daftar = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $daftar[$bulan] ?? '';
}

function bulan_singkat(int $bulan): string
{
    $daftar = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return $daftar[$bulan] ?? '';
}

function tanggal(mixed $date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $time = is_numeric($date) ? (int) $date : strtotime((string) $date);
    if ($time === false) {
        return '-';
    }

    $replace = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
        'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
        'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember',
        'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'Jun' => 'Jun',
        'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
    ];

    return strtr(date($format, $time), $replace);
}

function tanggal_waktu(mixed $datetime, string $format = 'd M Y H:i'): string
{
    if (empty($datetime)) {
        return '-';
    }
    return tanggal($datetime, $format);
}

function waktu_relatif(mixed $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $time = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    if ($time === false) {
        return '-';
    }
    $diff = time() - $time;

    if ($diff < 60) {
        return 'baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    if ($diff < 172800) {
        return 'kemarin ' . date('H:i', $time);
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' hari lalu';
    }
    return tanggal($time, 'd M Y');
}

function str_limit(mixed $value, int $limit = 60, string $end = '…'): string
{
    $text = trim((string) $value);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return mb_substr($text, 0, $limit) . $end;
}

/* ------------------------------------------------------------------ badge */

function status_badge(string $status): string
{
    $map = [
        'draft'       => ['netral', 'Draft'],
        'submitted'   => ['info', 'Diajukan'],
        'approved'    => ['sukses', 'Disetujui'],
        'posted'      => ['sukses', 'Posted'],
        'partial'     => ['warning', 'Sebagian'],
        'paid'        => ['sukses', 'Lunas'],
        'dibayar'     => ['sukses', 'Dibayar'],
        'overdue'     => ['bahaya', 'Jatuh Tempo'],
        'rejected'    => ['bahaya', 'Ditolak'],
        'returned'    => ['warning', 'Dikembalikan'],
        'pending'     => ['warning', 'Menunggu'],
        'reversed'    => ['bahaya', 'Dibalik'],
        'void'        => ['netral', 'Dibatalkan'],
        'cancelled'   => ['netral', 'Dibatalkan'],
        'active'      => ['sukses', 'Aktif'],
        'aktif'       => ['sukses', 'Aktif'],
        'open'        => ['sukses', 'Terbuka'],
        'closed'      => ['netral', 'Ditutup'],
        'locked'      => ['bahaya', 'Terkunci'],
        'cair'        => ['info', 'Dicairkan'],
        'pertanggungjawaban' => ['warning', 'Pertanggungjawaban'],
        'selesai'     => ['sukses', 'Selesai'],
        'berjalan'    => ['info', 'Berjalan'],
        'perencanaan' => ['netral', 'Perencanaan'],
        'batal'       => ['netral', 'Batal'],
        'arsip'       => ['netral', 'Arsip'],
        'nonaktif'    => ['netral', 'Nonaktif'],
        'dijual'      => ['warning', 'Dijual'],
        'dihapus'     => ['bahaya', 'Dihapus'],
        'reported'    => ['info', 'Dilaporkan'],
        'final'       => ['sukses', 'Final'],
        'dilaporkan'  => ['info', 'Dilaporkan'],
        'terbuka'     => ['sukses', 'Terbuka'],
        'aman'        => ['sukses', 'Aman'],
        'waspada'     => ['warning', 'Waspada'],
        'over'        => ['bahaya', 'Melebihi Pagu'],
    ];

    [$variant, $label] = $map[$status] ?? ['netral', ucfirst($status)];

    return '<span class="badge badge--' . $variant . '">' . e($label) . '</span>';
}

function badge(string $label, string $variant = 'netral'): string
{
    return '<span class="badge badge--' . e($variant) . '">' . e($label) . '</span>';
}

function progress_variant(float $percent): string
{
    if ($percent >= 100) {
        return 'bahaya';
    }
    if ($percent >= 85) {
        return 'warning';
    }
    return 'primary';
}

/* ---------------------------------------------------------------- periodic */

function periode_aktif(): array
{
    return \Lju\Services\PeriodService::current();
}

function periode_label(?array $periode = null): string
{
    $periode ??= periode_aktif();
    return (string) ($periode['nama'] ?? '-') . ' (' . (string) ($periode['kode'] ?? '-') . ')';
}

/* ------------------------------------------------------------------- misc */

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function device_is_mobile(): bool
{
    $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return (bool) preg_match('/(android|iphone|ipad|mobile|opera mini)/', $agent);
}

function audit_log(string $action, string $module, array $options = []): void
{
    Audit::log($action, $module, $options);
}
