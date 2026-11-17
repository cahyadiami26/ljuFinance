<?php
/**
 * Layout utama: sidebar + topbar + konten.
 *
 * @var string $content
 * @var string $pageTitle
 * @var string $pageSubtitle
 * @var string $pageActions
 * @var string $pageMeta
 * @var array  $breadcrumbs
 */

use Lju\Core\Db;
use Lju\Core\View;

$user = auth_user();
$menu = require APP_PATH . '/Config/menu.php';
$flashes = flash_messages();

$approvalBadge = 0;
try {
    $approvalBadge = (int) Db::val("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'");
} catch (\Throwable) {
    $approvalBadge = 0;
}

$unread = 0;
try {
    $unread = (int) Db::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [auth_id()]);
} catch (\Throwable) {
    $unread = 0;
}

$appName = (string) setting('app_name', config('app.name', 'LJU Finance'));
$appSubtitle = (string) setting('app_subtitle', config('app.subtitle', 'Finance & Accounting System'));
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions = $pageActions ?? '';
$pageMeta = $pageMeta ?? '';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="<?= e(logo_url()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?= View::partial('partials/sidebar', ['menu' => $menu, 'approvalBadge' => $approvalBadge]) ?>

    <div class="main">
        <?= View::partial('partials/topbar', [
            'user'          => $user,
            'breadcrumbs'   => $breadcrumbs,
            'approvalBadge' => $approvalBadge,
            'unread'        => $unread,
        ]) ?>

        <main class="content">
            <?= View::partial('partials/flash', ['flashes' => $flashes]) ?>

            <?php if (!empty($headerView)): ?>
                <?= View::partial((string) $headerView, get_defined_vars()) ?>
            <?php else: ?>
                <div class="page-head">
                    <div>
                        <h1 class="page-title"><?= e($pageTitle) ?></h1>
                        <?php if ($pageSubtitle !== ''): ?>
                            <div class="page-sub"><?= e($pageSubtitle) ?></div>
                        <?php endif; ?>
                        <?php if ($pageMeta !== ''): ?>
                            <div class="page-sub"><?= $pageMeta ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (trim($pageActions) !== ''): ?>
                        <div class="page-actions"><?= $pageActions ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>

        <div class="footer-bar">
            <span>&copy; <?= date('Y') ?> <?= e((string) setting('company_name', 'PT. Lampung Jasa Utama')) ?> · <?= e($appName) ?> v<?= e((string) setting('app_version', config('app.version', '1.0.0'))) ?></span>
            <span>Zona waktu Asia/Jakarta · Data per <?= e(tanggal_waktu(now())) ?> WIB</span>
        </div>
    </div>
</div>
<div class="sidebar-backdrop" data-sidebar-close></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
