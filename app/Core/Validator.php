<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Validator ringan dengan aturan berbasis string.
 *
 * Contoh:
 *   $errors = Validator::make($_POST, [
 *       'kode' => 'required|max:20|unique:ms_bidang,kode',
 *       'nama' => 'required|max:100',
 *   ], $ignoreId);
 */
final class Validator
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @return array<string,string> pesan error per field
     */
    public static function make(array $data, array $rules, ?int $ignoreId = null): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $rulesList = array_filter(array_map('trim', explode('|', (string) $ruleString)));
            $nullable = in_array('nullable', $rulesList, true);
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');

            foreach ($rulesList as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, '');
                $params = $param === '' ? [] : explode(',', $param);

                if ($name === 'required') {
                    if ($isEmpty) {
                        $errors[$field] = 'Wajib diisi.';
                        continue 2;
                    }
                    continue;
                }

                if ($isEmpty) {
                    if ($nullable) {
                        continue 2;
                    }
                    continue;
                }

                switch ($name) {
                    case 'max':
                        if (mb_strlen((string) $value) > (int) ($params[0] ?? 0)) {
                            $errors[$field] = 'Maksimal ' . ($params[0] ?? '') . ' karakter.';
                        }
                        break;

                    case 'min':
                        if (mb_strlen((string) $value) < (int) ($params[0] ?? 0)) {
                            $errors[$field] = 'Minimal ' . ($params[0] ?? '') . ' karakter.';
                        }
                        break;

                    case 'numeric':
                        if (!is_numeric(str_replace([',', '.'], '', (string) $value))) {
                            $errors[$field] = 'Harus berupa angka.';
                        }
                        break;

                    case 'integer':
                        if (!preg_match('/^-?\d+$/', (string) $value)) {
                            $errors[$field] = 'Harus berupa bilangan bulat.';
                        }
                        break;

                    case 'email':
                        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Format email tidak valid.';
                        }
                        break;

                    case 'date':
                        if (strtotime((string) $value) === false) {
                            $errors[$field] = 'Format tanggal tidak valid.';
                        }
                        break;

                    case 'in':
                        if (!in_array((string) $value, $params, true)) {
                            $errors[$field] = 'Pilihan tidak valid.';
                        }
                        break;

                    case 'alpha_dash':
                        if (!preg_match('/^[A-Za-z0-9 _\.\-\/]+$/', (string) $value)) {
                            $errors[$field] = 'Hanya boleh huruf, angka, spasi, titik, garis miring, dan tanda hubung.';
                        }
                        break;

                    case 'unique':
                        $table = $params[0] ?? '';
                        $column = $params[1] ?? '';
                        if ($table === '' || $column === '') {
                            break;
                        }
                        $sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = ?';
                        $bind = [$value];
                        if ($ignoreId !== null && $ignoreId > 0) {
                            $sql .= ' AND `id` <> ?';
                            $bind[] = $ignoreId;
                        }
                        if ((int) Db::val($sql, $bind) > 0) {
                            $errors[$field] = 'Nilai ini sudah dipakai.';
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}
