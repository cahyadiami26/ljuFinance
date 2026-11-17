<?php
/**
 * Profil pengguna.
 *
 * @var array $profile
 * @var array $roles
 * @var array $permissions
 * @var array $recentActivity
 * @var array $session
 */
$avatar = avatar_url($profile);
?>
<div class="grid-main">
    <div class="card">
        <div class="card-body" style="padding-top:20px">
            <div class="row" style="gap:16px">
                <span class="avatar avatar--lg">
                    <?php if ($avatar): ?><img src="<?= e($avatar) ?>" alt="" onerror="this.remove()"><?php endif; ?>
                    <span><?= e(inisial((string) $profile['name'])) ?></span>
                </span>
                <div>
                    <div class="strong" style="font-size:18px;letter-spacing:-.02em"><?= e((string) $profile['name']) ?></div>
                    <div class="small muted"><?= e((string) ($profile['position'] ?? '—')) ?> · <?= e((string) ($profile['nama_bidang'] ?? '—')) ?></div>
                    <div class="row" style="gap:6px;margin-top:8px">
                        <?php foreach ($roles as $role): ?>
                            <span class="badge badge--primary"><?= e((string) $role['name']) ?></span>
                        <?php endforeach; ?>
                        <?php if ($roles === []): ?><?= badge('Belum ada role', 'warning') ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <dl class="kv">
                <dt>NIK</dt><dd><?= e((string) ($profile['nik'] ?? '—')) ?></dd>
                <dt>Username</dt><dd><?= e((string) $profile['username']) ?></dd>
                <dt>Email</dt><dd><?= e((string) ($profile['email'] ?? '—')) ?></dd>
                <dt>Jabatan</dt><dd><?= e((string) ($profile['position'] ?? '—')) ?></dd>
                <dt>Nama Bidang</dt><dd><?= e((string) ($profile['nama_bidang'] ?? '—')) ?></dd>
                <dt>Nama Divisi</dt><dd><?= e((string) ($profile['nama_divisi'] ?? '—')) ?></dd>
                <dt>Role SSO</dt><dd><?= e((string) ($profile['sso_role'] ?? '—')) ?></dd>
                <dt>Login terakhir</dt><dd><?= $profile['last_login_at'] ? e(tanggal_waktu($profile['last_login_at'])) : '—' ?></dd>
                <dt>Sesi</dt><dd>ID <?= e((string) ($session['user_id'] ?? '—')) ?> · <?= e((string) ($session['username'] ?? '—')) ?></dd>
            </dl>

            <form method="post" action="<?= e(url('profile/sync')) ?>" class="mt-16">
                <?= csrf_field() ?>
                <button class="btn" type="submit"><?= icon('refresh', 15) ?> Sinkronkan Ulang dari SSO</button>
                <a class="btn btn--ghost" href="<?= e(url('logout')) ?>"><?= icon('logout', 15) ?> Keluar</a>
            </form>
        </div>
    </div>

    <div class="stack-8" style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Kewenangan Aktif</h2>
                    <div class="card-sub"><?= count($permissions) ?> permission pada role Anda.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row wrap" style="gap:6px">
                    <?php foreach ($permissions as $permission): ?>
                        <span class="badge badge--netral"><?= e($permission) ?></span>
                    <?php endforeach; ?>
                    <?php if ($permissions === []): ?>
                        <span class="muted small">Belum ada permission. Hubungi administrator.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Aktivitas Terakhir</h2>
                    <div class="card-sub">10 catatan audit terakhir atas nama Anda.</div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($recentActivity === []): ?>
                    <div class="empty-state" style="padding:24px 0">
                        <p>Belum ada aktivitas tercatat.</p>
                    </div>
                <?php else: ?>
                    <div class="feed">
                        <?php foreach ($recentActivity as $log): ?>
                            <div class="feed-item">
                                <span class="feed-icon feed-icon--primary"><?= icon('history', 15) ?></span>
                                <div class="feed-body">
                                    <div class="feed-title"><?= e(str_limit((string) ($log['description'] ?? ucfirst((string) $log['action'])), 60)) ?></div>
                                    <div class="feed-meta">
                                        <span><?= e(ucfirst((string) $log['action'])) ?></span>
                                        <span>· <?= e((string) $log['module']) ?></span>
                                    </div>
                                </div>
                                <span class="feed-time"><?= e(waktu_relatif($log['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
