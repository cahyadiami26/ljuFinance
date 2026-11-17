<?php
/**
 * Topbar: breadcrumb, pencarian, periode, notifikasi, profil.
 *
 * @var array $user
 * @var array $breadcrumbs
 * @var int   $unread
 */

$periode = periode_aktif();
$avatar = avatar_url($user);
$initial = inisial((string) ($user['name'] ?? 'Pengguna'));
$periodeTahun = (int) ($periode['tahun'] ?? date('Y'));
$periodeBulan = (int) ($periode['bulan'] ?? date('n'));
$statusPeriode = (string) ($periode['status'] ?? 'open');
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="icon-btn sidebar-toggle" type="button" data-sidebar-toggle aria-label="Buka menu">
            <?= icon('menu', 18) ?>
        </button>

        <div class="product">
            <strong><?= e((string) setting('app_name', 'LJU Finance')) ?></strong>
            <span>Finance</span>
        </div>

        <div class="crumbs">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?><span class="sep">›</span><?php endif; ?>
                <?php if (!empty($crumb['url'])): ?>
                    <a href="<?= e((string) $crumb['url']) ?>"><?= e((string) $crumb['label']) ?></a>
                <?php else: ?>
                    <span class="<?= $index === count($breadcrumbs) - 1 ? 'current' : '' ?>"><?= e((string) $crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <form class="topbar-search" method="get" action="<?= e(url('master/coa')) ?>" role="search">
        <?= icon('search', 16) ?>
        <input type="search" name="q" placeholder="Cari akun, COA, voucher…" value="<?= e((string) input('q', '')) ?>" aria-label="Pencarian">
        <kbd>⌘K</kbd>
    </form>

    <div class="topbar-actions">
        <div class="chip chip--period" title="Periode akuntansi aktif">
            <span class="chip-top">TA <?= e((string) $periodeTahun) ?></span>
            <span class="chip-bottom"><?= e(bulan_singkat($periodeBulan)) ?> <?= e((string) $periodeTahun) ?> · <?= e($statusPeriode === 'open' ? 'Open' : ucfirst($statusPeriode)) ?></span>
        </div>

        <span class="chip"><?= icon('coins', 15) ?> <?= e((string) setting('currency', 'IDR')) ?></span>

        <a class="btn btn--primary" href="<?= e(url('journal/create')) ?>">
            <?= icon('plus', 16) ?> Transaksi Baru
        </a>

        <a class="icon-btn" href="<?= e(url('approval')) ?>" aria-label="Notifikasi">
            <?= icon('bell', 18) ?>
            <?php if ($unread > 0): ?><span class="dot-badge"></span><?php endif; ?>
        </a>

        <a class="profile" href="<?= e(url('profile')) ?>" aria-label="Profil">
            <span class="avatar">
                <?php if ($avatar): ?>
                    <img src="<?= e($avatar) ?>" alt="<?= e((string) ($user['name'] ?? '')) ?>" onerror="this.remove()">
                <?php endif; ?>
                <span><?= e($initial) ?></span>
            </span>
            <span class="profile-meta">
                <span class="profile-name"><?= e(str_limit((string) ($user['name'] ?? 'Pengguna'), 22)) ?></span>
                <span class="profile-role"><?= e(str_limit((string) ($user['position'] ?? 'Pengguna'), 26)) ?></span>
            </span>
        </a>
    </div>
</header>
