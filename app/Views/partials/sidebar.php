<?php
/**
 * Sidebar navigasi.
 *
 * @var array $menu
 * @var int   $approvalBadge
 */

use Lju\Core\Router;

$path = Router::resolvePath();

$isActive = static function (string $route) use ($path): bool {
    if ($route === 'dashboard') {
        return $path === 'dashboard' || $path === '' || $path === 'dashboard/refresh';
    }
    return $path === $route || str_starts_with($path, $route . '/');
};
?>
<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <span class="fallback">LJU</span>
            <img src="<?= e(logo_url()) ?>" alt="LJU" onerror="this.style.display='none'">
        </div>
        <div class="brand-text">
            <div class="brand-name"><?= e((string) setting('app_name', 'LJU Finance')) ?></div>
            <div class="brand-sub">FMS Enterprise</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <?php if (($item['type'] ?? 'link') === 'link'): ?>
                <?php if (isset($item['permission']) && !can((string) $item['permission'])) { continue; } ?>
                <a class="nav-item <?= $isActive((string) $item['route']) ? 'active' : '' ?>"
                   href="<?= e(url((string) $item['route'])) ?>">
                    <span class="nav-icon"><?= icon((string) $item['icon'], 18) ?></span>
                    <span class="nav-label"><?= e((string) $item['label']) ?></span>
                    <?php if (($item['badge'] ?? '') === 'approval' && $approvalBadge > 0): ?>
                        <span class="nav-badge"><?= (int) $approvalBadge ?></span>
                    <?php elseif (!empty($item['stage'])): ?>
                        <span class="nav-dot" title="Dijadwalkan pada tahap <?= (int) $item['stage'] ?>"></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <?php
                $visibleItems = array_values(array_filter(
                    $item['items'] ?? [],
                    static fn ($sub) => !isset($sub['permission']) || can((string) $sub['permission'])
                ));
                if ($visibleItems === []) {
                    continue;
                }
                $groupActive = false;
                foreach ($visibleItems as $sub) {
                    if ($isActive((string) $sub['route'])) {
                        $groupActive = true;
                        break;
                    }
                }
                ?>
                <details class="nav-group" <?= $groupActive ? 'open' : '' ?>>
                    <summary>
                        <span class="nav-icon"><?= icon((string) $item['icon'], 18) ?></span>
                        <span class="nav-label"><?= e((string) $item['label']) ?></span>
                        <span class="nav-chevron"><?= icon('chevron-right', 14) ?></span>
                    </summary>
                    <div class="nav-items">
                        <?php foreach ($visibleItems as $sub): ?>
                            <a class="nav-item <?= $isActive((string) $sub['route']) ? 'active' : '' ?>"
                               href="<?= e(url((string) $sub['route'])) ?>">
                                <span class="nav-label"><?= e((string) $sub['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <span>v<?= e((string) setting('app_version', '1.0.0')) ?> Enterprise</span>
        <span class="foot-status"><span class="dot"></span>Online</span>
    </div>
</aside>
