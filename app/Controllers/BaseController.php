<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Auth;
use Lju\Core\View;

/**
 * Basis controller: helper render, permission, dan konfigurasi resource.
 */
abstract class BaseController
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $resources = null;

    /** @return array<string,mixed> */
    protected function resource(string $key): array
    {
        if (self::$resources === null) {
            self::$resources = require APP_PATH . '/Config/resources.php';
        }
        if (!isset(self::$resources[$key])) {
            App::abort(404, 'Master data "' . e($key) . '" tidak dikenal.');
        }
        return self::$resources[$key];
    }

    protected function requireCan(string $permission): void
    {
        if (!Auth::can($permission)) {
            App::abort(403, 'Anda tidak memiliki hak akses untuk aksi ini.');
        }
    }

    /**
     * Render halaman lengkap dengan layout.
     *
     * @param array<string,mixed> $data
     */
    protected function page(string $view, array $data = []): void
    {
        $defaults = [
            'pageTitle'   => '',
            'pageSubtitle' => '',
            'breadcrumbs' => [],
            'pageActions' => '',
            'activeRoute' => \Lju\Core\Router::resolvePath(),
            'bodyClass'   => '',
        ];

        View::page($view, array_merge($defaults, $data));
    }

    /** Nilai uang dari input (menerima "1.234.567,89" atau "1234567.89"). */
    protected function toDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }
        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $value) ?? '';
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }
        return number_format((float) $clean, 2, '.', '');
    }

    /** Data form dari definisi field. */
    protected function collectInput(array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'checkbox') {
                $data[$name] = (int) (bool) input($name);
                continue;
            }

            $value = input($name);

            if ($type === 'money') {
                $data[$name] = $this->toDecimal($value);
                continue;
            }

            if ($type === 'number') {
                $data[$name] = $value === null || $value === '' ? 0 : (float) str_replace(',', '.', (string) $value);
                continue;
            }

            if ($value === '' || $value === null) {
                $data[$name] = !empty($field['required']) ? null : ($field['nullable'] ?? false ? null : null);
                continue;
            }

            $data[$name] = $value;
        }
        return $data;
    }

    /**
     * Opsi select dinamis untuk field options_sql.
     *
     * @return array<string,array<int,array{value:string,label:string}>>
     */
    protected function fieldOptions(array $fields): array
    {
        $options = [];
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            if (($field['type'] ?? '') !== 'select') {
                continue;
            }
            if (isset($field['options']) && is_array($field['options'])) {
                $list = [];
                foreach ($field['options'] as $value => $label) {
                    $list[] = ['value' => (string) $value, 'label' => (string) $label];
                }
                $options[$name] = $list;
                continue;
            }
            if (!empty($field['options_sql'])) {
                $rows = \Lju\Core\Db::all((string) $field['options_sql']);
                $list = [];
                foreach ($rows as $row) {
                    $values = array_values($row);
                    $list[] = [
                        'value' => (string) ($row['id'] ?? $values[0] ?? ''),
                        'label' => (string) ($row['nama'] ?? $row['name'] ?? $values[1] ?? ''),
                    ];
                }
                $options[$name] = $list;
            }
        }
        return $options;
    }
}
