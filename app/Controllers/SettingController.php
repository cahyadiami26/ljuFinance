<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Pengaturan aplikasi berbasis key-value.
 */
final class SettingController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('settings.manage');

        $settings = Db::all('SELECT * FROM app_settings ORDER BY group_name, sort_order, setting_key');
        $groups = [];
        foreach ($settings as $setting) {
            $groups[(string) $setting['group_name']][] = $setting;
        }

        $this->page('pages/settings/index', [
            'pageTitle'    => 'Pengaturan Aplikasi',
            'pageSubtitle' => 'Identitas perusahaan, kebijakan keuangan, dan parameter sistem.',
            'breadcrumbs'  => [
                ['label' => 'System Admin', 'url' => null],
                ['label' => 'Pengaturan', 'url' => null],
            ],
            'groups' => $groups,
            'labels' => [
                'identitas'   => 'Identitas Perusahaan',
                'keuangan'    => 'Kebijakan Keuangan',
                'anggaran'    => 'Kontrol Anggaran',
                'approval'    => 'Persetujuan',
                'akuntansi'   => 'Akuntansi & Periode',
                'laporan'     => 'Laporan & Cetak',
                'sistem'      => 'Sistem',
            ],
        ]);
    }

    public function update(): void
    {
        $this->requireCan('settings.manage');

        $keys = array_column(Db::all('SELECT setting_key FROM app_settings'), 'setting_key');
        $changed = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $newValue = $_POST[$key];
            $newValue = is_string($newValue) ? trim($newValue) : (string) $newValue;

            $oldValue = (string) Db::val('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key], '');
            if ($oldValue === $newValue) {
                continue;
            }

            Db::exec(
                'UPDATE app_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?',
                [$newValue, auth_id(), $key]
            );
            $changed[$key] = ['old' => $oldValue, 'new' => $newValue];
        }

        if ($changed !== []) {
            Audit::log('update', 'settings', [
                'ref_table'   => 'app_settings',
                'description' => 'Mengubah ' . count($changed) . ' pengaturan aplikasi',
                'old'         => array_map(static fn ($v) => $v['old'], $changed),
                'new'         => array_map(static fn ($v) => $v['new'], $changed),
            ]);
            flash_success(count($changed) . ' pengaturan berhasil disimpan.');
        } else {
            flash_info('Tidak ada perubahan pengaturan.');
        }

        redirect('system/settings');
    }
}
