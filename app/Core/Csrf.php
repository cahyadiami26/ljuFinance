<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Proteksi CSRF untuk seluruh permintaan POST.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals(self::token(), $token);
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
