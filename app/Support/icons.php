<?php
/**
 * Ikon SVG inline (tanpa dependensi eksternal).
 * Penggunaan: icon('dashboard', 18) atau icon('bell', 16, 'nav-icon').
 */

declare(strict_types=1);

if (!function_exists('icon')) {
    /** @param array<string,string> $overrides */
    function icon(string $name, int $size = 18, string $class = '', array $overrides = []): string
    {
        static $icons = null;

        if ($icons === null) {
            $icons = [
                'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
                'database'  => '<ellipse cx="12" cy="5" rx="8.5" ry="3"/><path d="M3.5 5v6c0 1.66 3.8 3 8.5 3s8.5-1.34 8.5-3V5"/><path d="M3.5 11v6c0 1.66 3.8 3 8.5 3s8.5-1.34 8.5-3v-6"/>',
                'list'      => '<line x1="9" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="9" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1.1"/><circle cx="4" cy="12" r="1.1"/><circle cx="4" cy="18" r="1.1"/>',
                'org'       => '<rect x="9" y="2.5" width="6" height="5" rx="1"/><rect x="2" y="16.5" width="6" height="5" rx="1"/><rect x="16" y="16.5" width="6" height="5" rx="1"/><path d="M12 7.5v4M5 16.5v-3h14v3"/>',
                'grid'      => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
                'layers'    => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 13 9 5 9-5"/>',
                'users'     => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7" r="3.5"/><path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16 3.6a4 4 0 0 1 0 7.75"/>',
                'briefcase' => '<rect x="2.5" y="7.5" width="19" height="12" rx="2.5"/><path d="M8.5 7.5V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v1.5"/><path d="M2.5 12.5h19"/>',
                'bank'      => '<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8"/><path d="M3 21h18"/>',
                'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
                'chart'     => '<rect x="3" y="12" width="4" height="9" rx="1"/><rect x="10" y="7" width="4" height="14" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>',
                'wallet'    => '<path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H19a2 2 0 0 1 2 2v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17V8.5Z"/><path d="M3 9h14"/><path d="M16.5 12.5h3"/>',
                'arrow-down'=> '<line x1="12" y1="4" x2="12" y2="19"/><polyline points="6 13 12 19 18 13"/>',
                'arrow-up'  => '<line x1="12" y1="20" x2="12" y2="5"/><polyline points="6 11 12 5 18 11"/>',
                'arrow-right'=> '<line x1="4" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/>',
                'arrow-left' => '<line x1="20" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/>',
                'book'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5A2.5 2.5 0 0 1 4 20.5Z"/>',
                'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
                'scale'     => '<path d="M12 4v16M6 20h12"/><path d="m12 7-6 3 3 5 3-5Z"/><path d="m12 7 6 3-3 5-3-5Z"/>',
                'lock'      => '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
                'asset'     => '<path d="M3 8.5 12 3.5l9 5v7l-9 5-9-5v-7Z"/><path d="M3 8.5 12 13.5l9-5M12 13.5V21"/>',
                'receipt'   => '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
                'report'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
                'check-circle' => '<circle cx="12" cy="12" r="9"/><polyline points="8.5 12.5 11 15 15.5 9.5"/>',
                'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.5 14.5a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.3 1Z"/>',
                'shield'    => '<path d="M12 3 5 6v6c0 4 3 7.4 7 9 4-1.6 7-5 7-9V6l-7-3Z"/><polyline points="9 12 11.5 14.5 15.5 10"/>',
                'history'   => '<path d="M3 12a9 9 0 1 0 2.6-6.4"/><polyline points="3 4 3 9.5 8.5 9.5"/><polyline points="12 8 12 12.5 15.5 14.5"/>',
                'sliders'   => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/><circle cx="9" cy="7" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="17" r="2"/>',
                'search'    => '<circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/>',
                'bell'      => '<path d="M18 8a6 6 0 1 0-12 0c0 5-2 6-2 6h16s-2-1-2-6"/><path d="M10.5 20a2 2 0 0 0 3 0"/>',
                'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
                'download'  => '<path d="M12 3v12"/><polyline points="7 11 12 16 17 11"/><path d="M5 20h14"/>',
                'refresh'   => '<path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"/><path d="M3 21v-5h5"/>',
                'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
                'chevron-right'=> '<polyline points="9 6 15 12 9 18"/>',
                'alert'     => '<path d="M12 4 2.8 20h18.4L12 4Z"/><line x1="12" y1="10" x2="12" y2="14"/><circle cx="12" cy="17" r=".7"/>',
                'info'      => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16.5"/><circle cx="12" cy="8" r=".7"/>',
                'check'     => '<polyline points="4 12.5 9.5 18 20 6"/>',
                'x'         => '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>',
                'eye'       => '<path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.8"/>',
                'power'     => '<path d="M12 3v9"/><path d="M6.5 6.5a8 8 0 1 0 11 0"/>',
                'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><polyline points="9 15 4 12 9 9"/><line x1="4" y1="12" x2="15" y2="12"/>',
                'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
                'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3.5 6.5 12 13 20.5 6.5"/>',
                'phone'     => '<path d="M6 3h3l2 5-2.5 1.5a11 11 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2 2A15 15 0 0 1 4 5a2 2 0 0 1 2-2Z"/>',
                'building'  => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1"/><path d="M10 21v-2h4v2"/>',
                'clock'     => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12.5 16 14.5"/>',
                'filter'    => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>',
                'save'      => '<path d="M5 3h11l3 3v15H5V3Z"/><path d="M8 3v6h7V3"/><rect x="8" y="13" width="8" height="8"/>',
                'menu'      => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
                'trending-up' => '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/>',
                'external'  => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5H10"/>',
                'file'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>',
                'coins'     => '<ellipse cx="9" cy="7" rx="5.5" ry="2.6"/><path d="M3.5 7v3.5c0 1.4 2.5 2.6 5.5 2.6s5.5-1.2 5.5-2.6V7"/><path d="M14.5 12.4c2.7.2 4.8 1.3 4.8 2.6 0 1.4-2.5 2.6-5.5 2.6-2.3 0-4.3-.8-5.1-1.8"/><path d="M14.5 12.4v3.5"/>',
            ];
        }

        $paths = $overrides[$name] ?? $icons[$name] ?? $icons['info'];
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"%s aria-hidden="true">%s</svg>',
            $size,
            $size,
            $classAttr,
            $paths
        );
    }
}
