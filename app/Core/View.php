<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Render view PHP polos dengan layout.
 */
final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @param array<string,mixed> $data */
    public static function share(string $key, mixed $data): void
    {
        self::$shared[$key] = $data;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($template, $data);

        if ($layout === null) {
            return $content;
        }

        $data['content'] = $content;
        return self::partial($layout, $data);
    }

    /**
     * @param array<string,mixed> $__data
     */
    public static function partial(string $__template, array $__data = []): string
    {
        $__file = VIEW_PATH . '/' . str_replace('.', '/', $__template) . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException('View tidak ditemukan: ' . $__template);
        }

        // Semua nama internal berprefiks ganda agar variabel view seperti
        // $data atau $template tetap dapat di-extract tanpa tertutup.
        $__vars = array_merge(self::$shared, $__data);

        ob_start();
        extract($__vars, EXTR_SKIP);
        require $__file;
        return (string) ob_get_clean();
    }

    /** Halaman penuh (dengan layout) langsung ditampilkan. */
    public static function page(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        echo self::render($template, $data, $layout);
    }
}
