<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Paginasi untuk daftar data.
 */
final class Paginator
{
    public int $total;
    public int $page;
    public int $perPage;

    public function __construct(int $total, int $page = 1, int $perPage = 15)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->page = max(1, $page);
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function offset(): int
    {
        return ($this->clampedPage() - 1) * $this->perPage;
    }

    public function clampedPage(): int
    {
        return min($this->page, $this->pages());
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /**
     * @param array<string,mixed> $query parameter tambahan (search, filter)
     */
    public function links(string $route, array $query = []): string
    {
        $pages = $this->pages();
        if ($pages <= 1) {
            return '';
        }

        $current = $this->clampedPage();
        $window = 2;
        $start = max(1, $current - $window);
        $end = min($pages, $current + $window);

        $link = static function (int $page, string $label, bool $active = false, bool $disabled = false) use ($route, $query): string {
            if ($disabled) {
                return '<span class="page-item disabled">' . $label . '</span>';
            }
            $query['page'] = $page;
            $href = url($route, $query);
            $class = 'page-item' . ($active ? ' active' : '');
            return '<a class="' . $class . '" href="' . e($href) . '">' . $label . '</a>';
        };

        $html = '<nav class="pagination">';
        $html .= $link(max(1, $current - 1), '&larr;', false, $current === 1);
        if ($start > 1) {
            $html .= $link(1, '1');
            if ($start > 2) {
                $html .= '<span class="page-item disabled">&hellip;</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $link($i, (string) $i, $i === $current);
        }
        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<span class="page-item disabled">&hellip;</span>';
            }
            $html .= $link($pages, (string) $pages);
        }
        $html .= $link(min($pages, $current + 1), '&rarr;', false, $current === $pages);
        $html .= '</nav>';

        return $html;
    }
}
