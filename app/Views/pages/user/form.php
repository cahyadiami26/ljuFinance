<?php
/**
 * Penetapan role pengguna.
 *
 * @var array $user
 * @var array $roles
 * @var array $assigned
 */
$avatar = avatar_url($user);
?>
<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Role Aplikasi</h2>
                <div class="card-sub">Role menentukan menu dan aksi yang boleh dilakukan pengguna.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('master/user/' . (int) $user['id'] . '/role')) ?>">
                <?= csrf_field() ?>
                <div class="stack-8">
                    <?php foreach ($roles as $role): ?>
                        <label class="checkbox" style="align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px">
                            <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>"
                                <?= in_array((int) $role['id'], $assigned, true) ? 'checked' : '' ?>>
                            <span>
                                <span class="strong" style="font-size:13px"><?= e((string) $role['name']) ?></span>
                                <span class="tiny muted" style="display:block;margin-top:2px">
                                    <?= e((string) ($role['description'] ?? '')) ?> ·
                                    <?= (int) ($role['jumlah_permission'] ?? 0) ?> permission
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> Simpan Hak Akses</button>
                    <a class="btn" href="<?= e(url('master/user')) ?>">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Profil Pengguna</h2>
        </div>
        <div class="card-body">
            <div class="row" style="gap:14px;margin-bottom:16px">
                <span class="avatar avatar--lg">
                    <?php if ($avatar): ?><img src="<?= e($avatar) ?>" alt="" onerror="this.remove()"><?php endif; ?>
                    <span><?= e(inisial((string) $user['name'])) ?></span>
                </span>
                <div>
                    <div class="strong" style="font-size:15px"><?= e((string) $user['name']) ?></div>
                    <div class="small muted"><?= e((string) ($user['position'] ?? '—')) ?></div>
                    <div class="tiny muted">NIK <?= e((string) ($user['nik'] ?? '—')) ?></div>
                </div>
            </div>

            <dl class="kv">
                <dt>Username</dt><dd><?= e((string) $user['username']) ?></dd>
                <dt>Email</dt><dd><?= e((string) ($user['email'] ?? '—')) ?></dd>
                <dt>Bidang</dt><dd><?= e((string) ($user['nama_bidang'] ?? '—')) ?></dd>
                <dt>Divisi</dt><dd><?= e((string) ($user['nama_divisi'] ?? '—')) ?></dd>
                <dt>Role SSO</dt><dd><?= e((string) ($user['sso_role'] ?? '—')) ?></dd>
                <dt>Status</dt><dd><?= ((int) $user['is_active'] === 1) ? badge('Aktif', 'sukses') : badge('Nonaktif', 'netral') ?></dd>
                <dt>Sinkron terakhir</dt><dd><?= $user['synced_at'] ? e(tanggal_waktu($user['synced_at'])) : '—' ?></dd>
            </dl>

            <div class="notice mt-16">
                <?= icon('info', 15) ?>
                <div>Data profil bersumber dari portal SSO. Perubahan nama, jabatan, atau unit dilakukan di portal, bukan di aplikasi ini.</div>
            </div>
        </div>
    </div>
</div>
