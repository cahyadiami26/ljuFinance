<?php
/**
 * Halaman pemilih pengguna — HANYA untuk mode pengembangan.
 * Di produksi login dilakukan melalui portal SSO lju.co.id.
 *
 * @var array $users
 */
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-logo">
                <span class="fallback">LJU</span>
                <img src="<?= e(logo_url()) ?>" alt="LJU" onerror="this.style.display='none'">
            </div>
            <div>
                <div class="strong" style="font-size:14px"><?= e((string) setting('app_name', 'LJU Finance')) ?></div>
                <div class="brand-sub" style="margin-top:1px">Finance &amp; Accounting System</div>
            </div>
        </div>

        <div class="auth-title">Pilih pengguna untuk masuk</div>
        <div class="auth-sub">
            Mode pengembangan aktif (<span class="code-pill">APP_ENV=local</span> + <span class="code-pill">dev_login=true</span>).
            Pada produksi, halaman ini tidak tersedia dan sesi dibuat oleh portal SSO.
        </div>

        <?php if ($users === []): ?>
            <div class="notice notice--warning mt-16">
                <?= icon('alert', 16) ?>
                <div>Belum ada pengguna pada tabel <span class="code-pill">app_users</span>. Import <span class="code-pill">database/seed/02_demo.sql</span> terlebih dahulu.</div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('login')) ?>" class="auth-list">
                <?= csrf_field() ?>
                <?php foreach ($users as $user): ?>
                    <?php $avatar = avatar_url($user); ?>
                    <button class="auth-user" type="submit" name="user_id" value="<?= (int) $user['id'] ?>">
                        <span class="avatar avatar--sm">
                            <?php if ($avatar): ?><img src="<?= e($avatar) ?>" alt="" onerror="this.remove()"><?php endif; ?>
                            <span><?= e(inisial((string) $user['name'])) ?></span>
                        </span>
                        <span class="meta">
                            <span class="nm"><?= e((string) $user['name']) ?></span>
                            <span class="rl"><?= e((string) ($user['position'] ?? '')) ?><?= !empty($user['role_names']) ? ' · ' . e((string) $user['role_names']) : '' ?></span>
                        </span>
                        <?= icon('chevron-right', 15) ?>
                    </button>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>

        <div class="tiny muted" style="margin-top:14px;text-align:center">
            Versi <?= e((string) setting('app_version', '1.0.0')) ?> · <?= e((string) setting('company_name', 'PT. Lampung Jasa Utama')) ?>
        </div>
    </div>
</div>
