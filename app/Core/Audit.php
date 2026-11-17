<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Audit trail immutable: siapa, kapan, apa, nilai lama & baru.
 */
final class Audit
{
    /**
     * @param array{
     *   ref_table?:string, ref_id?:int|string|null, ref_code?:string, description?:string,
     *   old?:mixed, new?:mixed
     * } $options
     */
    public static function log(string $action, string $module, array $options = []): void
    {
        $user = Auth::check() ? Auth::user() : [];

        try {
            Db::insert('audit_logs', [
                'user_id'     => (int) ($user['id'] ?? 0) ?: null,
                'user_name'   => (string) ($user['name'] ?? ($_SESSION['user_name'] ?? 'Sistem')),
                'action'      => strtolower($action),
                'module'      => $module,
                'ref_table'   => $options['ref_table'] ?? null,
                'ref_id'      => isset($options['ref_id']) ? (int) $options['ref_id'] : null,
                'ref_code'    => $options['ref_code'] ?? null,
                'description' => isset($options['description']) ? mb_substr((string) $options['description'], 0, 255) : null,
                'old_values'  => isset($options['old']) ? self::encode($options['old']) : null,
                'new_values'  => isset($options['new']) ? self::encode($options['new']) : null,
                'ip_address'  => self::ip(),
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // audit tidak boleh menggagalkan transaksi bisnis
            error_log('[LJU][audit] ' . $e->getMessage());
        }
    }

    private static function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? null : $json;
    }

    private static function ip(): ?string
    {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $value = explode(',', (string) $_SERVER[$key])[0];
                return mb_substr(trim($value), 0, 45);
            }
        }
        return null;
    }
}
