<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Utilitas aplikasi: base URL, URL saat ini, abort, dan penanganan error.
 */
final class App
{
    public static function baseUrl(): string
    {
        $configured = trim((string) Config::get('app.base_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '.' || $dir === '') {
            $dir = '';
        }
        return $dir;
    }

    public static function currentUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $titles = [
            400 => 'Permintaan tidak valid',
            401 => 'Tidak terautentikasi',
            403 => 'Akses ditolak',
            404 => 'Halaman tidak ditemukan',
            419 => 'Sesi kedaluwarsa',
            422 => 'Data tidak valid',
            500 => 'Terjadi kesalahan sistem',
        ];
        $title = $titles[$status] ?? 'Kesalahan';
        if ($message === '') {
            $message = $title;
        }

        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'status' => $status, 'message' => $message]);
            exit;
        }

        echo View::render('pages.error', [
            'status'      => $status,
            'title'       => $title,
            'message'     => $message,
            'pageTitle'   => $title,
            'breadcrumbs' => [],
        ], 'layouts/app');
        exit;
    }

    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    public static function handleException(\Throwable $e): void
    {
        $debug = (bool) Config::get('app.debug', false);

        if ($debug) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<div style="font:14px/1.6 ui-monospace,monospace;padding:24px;background:#0b1728;color:#e2e8f0">';
            echo '<h2 style="color:#f87171;margin:0 0 12px">' . htmlspecialchars(get_class($e)) . '</h2>';
            echo '<p style="color:#fca5a5">' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p style="color:#94a3b8">' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
            echo '<pre style="white-space:pre-wrap;color:#cbd5e1">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
            return;
        }

        error_log('[LJU] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        http_response_code(500);
        $message = $e instanceof \RuntimeException
            ? $e->getMessage()
            : 'Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi atau hubungi administrator.';

        try {
            echo View::render('pages.error', [
                'status'      => 500,
                'title'       => 'Terjadi kesalahan',
                'message'     => $message,
                'pageTitle'   => 'Terjadi kesalahan',
                'breadcrumbs' => [],
            ], 'layouts/app');
        } catch (\Throwable) {
            echo '<h1>500 — Terjadi kesalahan sistem</h1><p>' . htmlspecialchars($message) . '</p>';
        }
    }
}
