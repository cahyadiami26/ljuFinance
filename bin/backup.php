#!/usr/bin/env php
<?php
/**
 * Cadangan database dari CLI (cron / SSH).
 *
 * Contoh crontab harian pukul 01.00:
 *   0 1 * * * /usr/bin/php /path/ke/ljuFinance/bin/backup.php >> /path/ke/ljuFinance/storage/logs/backup.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari CLI.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $hasil = \Lju\Services\BackupService::buat('CLI/cron');
    printf(
        "[%s] Cadangan dibuat: %s (%d tabel, %d baris, %.1f KB, %.2f detik)\n",
        date('Y-m-d H:i:s'),
        $hasil['file'],
        $hasil['tables'],
        $hasil['rows'],
        $hasil['size'] / 1024,
        $hasil['duration']
    );
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Cadangan gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
