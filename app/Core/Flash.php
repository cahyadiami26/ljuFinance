<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Pesan sekali tampil (session flash).
 */
final class Flash
{
    private const KEY = '_flash';

    public static function success(string $message): void
    {
        self::push('success', $message);
    }

    public static function error(string $message): void
    {
        self::push('error', $message);
    }

    public static function warning(string $message): void
    {
        self::push('warning', $message);
    }

    public static function info(string $message): void
    {
        self::push('info', $message);
    }

    public static function push(string $type, string $message): void
    {
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pull(): array
    {
        $items = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return is_array($items) ? $items : [];
    }
}
