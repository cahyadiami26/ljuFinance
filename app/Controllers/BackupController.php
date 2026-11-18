<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Core\Flash;
use Lju\Services\BackupService;

/**
 * Cadangan database: daftar file, buat, unduh, dan hapus.
 */
final class BackupController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('settings.manage');

        $this->page('pages/settings/backup', [
            'pageTitle'    => 'Cadangan Database',
            'pageSubtitle' => 'Buat dan unduh salinan database tanpa tool eksternal.',
            'breadcrumbs'  => [
                ['label' => 'System Admin', 'url' => null],
                ['label' => 'Cadangan Database', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('system/settings')) . '">' . icon('settings', 15) . ' Pengaturan</a>',
            'daftar'       => BackupService::daftar(),
            'ringkasan'    => BackupService::ringkasan(),
            'folder'       => 'storage/backups',
            'retensi'      => (int) setting('backup_retention_days', 14),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('settings.manage');

        try {
            $hasil = BackupService::buat(trim((string) input('catatan', '')));
        } catch (\Throwable $e) {
            Flash::error('Cadangan gagal dibuat: ' . $e->getMessage());
            redirect('system/backup');
        }

        Audit::log('create', 'system.backup', [
            'ref_table'   => 'storage',
            'ref_code'    => $hasil['file'],
            'description' => 'Membuat cadangan database ' . $hasil['file']
                . ' (' . $hasil['tables'] . ' tabel, ' . $hasil['rows'] . ' baris)',
            'new'         => $hasil,
        ]);
        Flash::success(
            'Cadangan ' . $hasil['file'] . ' dibuat: ' . $hasil['tables'] . ' tabel, '
            . number_format($hasil['rows'], 0, ',', '.') . ' baris, '
            . number_format($hasil['size'] / 1024, 1, ',', '.') . ' KB dalam ' . $hasil['duration'] . ' detik.'
        );
        redirect('system/backup');
    }

    public function download(string $file): void
    {
        $this->requireCan('settings.manage');

        try {
            $path = BackupService::path($file);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('system/backup');
        }

        Audit::log('export', 'system.backup', [
            'ref_table'   => 'storage',
            'ref_code'    => $file,
            'description' => 'Mengunduh cadangan database ' . $file,
        ]);

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public function delete(string $file): void
    {
        $this->requireCan('settings.manage');

        try {
            BackupService::hapus($file);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('system/backup');
        }

        Audit::log('delete', 'system.backup', [
            'ref_table'   => 'storage',
            'ref_code'    => $file,
            'description' => 'Menghapus cadangan database ' . $file,
        ]);
        Flash::success('Cadangan ' . $file . ' dihapus.');
        redirect('system/backup');
    }
}
