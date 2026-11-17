<?php
/**
 * Daftar pengguna & hak akses.
 *
 * @var array $users
 * @var array $roles
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $roleFilter
 * @var bool $canManage
 */
?>
<div class="toolbar">
    <form class="search-box" method="get" action="<?= e(url('master/user')) ?>">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, NIK, username…">
        <input type="hidden" name="r" value="master/user">
        <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
    </form>

    <form method="get" action="<?= e(url('master/user')) ?>" class="row" style="gap:8px">
        <input type="hidden" name="r" value="master/user">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <select class="filter-select" name="role" onchange="this.form.submit()">
            <option value="">Semua role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= e((string) $role['code']) ?>" <?= $roleFilter === (string) $role['code'] ? 'selected' : '' ?>>
                    <?= e((string) $role['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> pengguna</span>
</div>

<div class="card card--flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Pengguna</th>
                    <th>Jabatan / Unit</th>
                    <th>Role Aplikasi</th>
                    <th>Login Terakhir</th>
                    <th>Status</th>
                    <?php if ($canManage): ?><th class="right">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $avatar = avatar_url($user); ?>
                    <tr>
                        <td>
                            <div class="row" style="gap:10px">
                                <span class="avatar avatar--sm">
                                    <?php if ($avatar): ?>
                                        <img src="<?= e($avatar) ?>" alt="" onerror="this.remove()">
                                    <?php endif; ?>
                                    <span><?= e(inisial((string) $user['name'])) ?></span>
                                </span>
                                <div class="person">
                                    <span class="person-name"><?= e((string) $user['name']) ?></span>
                                    <span class="person-role"><?= e((string) ($user['email'] ?? $user['username'])) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="person">
                                <span class="person-name" style="font-weight:500;font-size:12px"><?= e((string) ($user['position'] ?? '—')) ?></span>
                                <span class="person-role"><?= e(trim(($user['bidang_nama'] ?? $user['nama_bidang'] ?? '') . ' · ' . ($user['divisi_nama'] ?? $user['nama_divisi'] ?? ''), ' ·')) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($user['role_names'])): ?>
                                <span class="small strong"><?= e((string) $user['role_names']) ?></span>
                            <?php else: ?>
                                <?= badge('Belum ada role', 'warning') ?>
                            <?php endif; ?>
                        </td>
                        <td class="small muted"><?= $user['last_login_at'] ? e(waktu_relatif($user['last_login_at'])) : '—' ?></td>
                        <td><?= ((int) $user['is_active'] === 1) ? badge('Aktif', 'sukses') : badge('Nonaktif', 'netral') ?></td>
                        <?php if ($canManage): ?>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn--sm" href="<?= e(url('master/user/' . (int) $user['id'] . '/edit')) ?>">
                                        <?= icon('shield', 14) ?> Hak Akses
                                    </a>
                                    <form method="post" action="<?= e(url('master/user/' . (int) $user['id'] . '/toggle')) ?>"
                                          data-confirm="Ubah status pengguna <?= e((string) $user['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn--sm" type="submit"><?= icon('power', 14) ?></button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="list-foot">
        <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> pengguna</span>
        <?= $paginator->links('master/user', array_filter(['q' => $q, 'role' => $roleFilter])) ?>
    </div>
</div>
