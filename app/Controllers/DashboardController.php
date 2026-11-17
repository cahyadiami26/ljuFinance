<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Services\DashboardService;
use Lju\Services\PeriodService;

/**
 * Executive Dashboard (Direksi & manajemen).
 */
final class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->render(false);
    }

    public function refresh(): void
    {
        Audit::log('refresh', 'dashboard', ['description' => 'Menyegarkan data dashboard']);
        flash_success('Data dashboard telah disegarkan.');
        redirect('dashboard');
    }

    private function render(bool $refreshed): void
    {
        $periode = PeriodService::current();
        try {
            PeriodService::ensureYear((int) $periode['tahun']);
            PeriodService::ensureYear((int) $periode['tahun'] + 1);
        } catch (\Throwable) {
            // periode sudah ada / tabel belum siap
        }

        $data = DashboardService::data();

        $this->page('pages/dashboard', [
            'pageTitle'    => 'Dashboard',
            'pageSubtitle' => 'Ringkasan posisi keuangan, realisasi anggaran, dan kontrol operasional.',
            'headerView'   => 'partials/dashboard_head',
            'roleLabel'    => $this->roleLabel(),
            'breadcrumbs'  => [
                ['label' => 'Dashboard', 'url' => null],
                ['label' => 'Overview', 'url' => null],
            ],
            'data'       => $data,
            'periode'    => $periode,
            'greeting'   => $this->greeting(),
            'refreshed'  => $refreshed,
        ]);
    }

    /** Label role utama pengguna (untuk badge di header dashboard). */
    private function roleLabel(): string
    {
        $roles = \Lju\Core\Auth::roles();
        if ($roles === []) {
            return 'Pengguna';
        }
        $names = \Lju\Core\Db::all(
            'SELECT name FROM app_roles WHERE code IN (' . implode(',', array_fill(0, count($roles), '?')) . ') ORDER BY level',
            $roles
        );
        $labels = array_column($names, 'name');
        return $labels !== [] ? (string) $labels[0] : ucfirst((string) $roles[0]);
    }

    private function greeting(): string
    {
        $hour = (int) date('H');
        if ($hour < 11) {
            return 'Selamat Pagi';
        }
        if ($hour < 15) {
            return 'Selamat Siang';
        }
        if ($hour < 18) {
            return 'Selamat Sore';
        }
        return 'Selamat Malam';
    }
}
