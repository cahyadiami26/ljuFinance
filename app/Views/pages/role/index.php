<?php
/**
 * Role & permission matrix.
 *
 * @var array $roles
 * @var array $permissions
 * @var array $userCounts
 * @var array $groups
 */
?>
<div class="notice notice--info mb-16">
    <?= icon('shield', 16) ?>
    <div>
        <strong>Matriks kewenangan.</strong>
        Centang permission yang boleh dilakukan pada tiap role, lalu simpan. Super Administrator selalu memiliki seluruh akses.
    </div>
</div>

<?php foreach ($roles as $index => $role): ?>
    <?php $rolePermissions = $permissions[(int) $role['id']] ?? []; ?>
    <details class="card mb-12" <?= $index === 0 ? 'open' : '' ?> style="overflow:hidden">
        <summary style="list-style:none;cursor:pointer;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px">
            <div class="row" style="gap:12px">
                <span class="kpi-icon"><?= icon('shield', 16) ?></span>
                <div>
                    <div class="card-title"><?= e((string) $role['name']) ?></div>
                    <div class="card-sub"><?= e((string) ($role['description'] ?? '')) ?> · kode <span class="code-pill"><?= e((string) $role['code']) ?></span></div>
                </div>
            </div>
            <div class="row" style="gap:8px">
                <span class="badge badge--netral"><?= (int) ($userCounts[(int) $role['id']] ?? 0) ?> pengguna</span>
                <span class="badge badge--primary"><?= count($rolePermissions) ?> permission</span>
                <span class="muted"><?= icon('chevron-down', 16) ?></span>
            </div>
        </summary>

        <form method="post" action="<?= e(url('master/role/' . (int) $role['id'])) ?>" style="border-top:1px solid var(--border)">
            <?= csrf_field() ?>
            <div class="card-body" style="padding:18px 20px">
                <div class="grid-3">
                    <?php foreach ($groups as $groupName => $items): ?>
                        <div>
                            <div class="summary-label" style="margin-bottom:8px"><?= e($groupName) ?></div>
                            <div class="stack-4">
                                <?php foreach ($items as $permission => $label): ?>
                                    <label class="checkbox" style="font-size:12px">
                                        <input type="checkbox" name="permissions[]" value="<?= e($permission) ?>"
                                            <?= in_array($permission, $rolePermissions, true) ? 'checked' : '' ?>>
                                        <span><?= e($label) ?> <span class="tiny muted"><?= e($permission) ?></span></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-actions" style="margin:0;padding:14px 20px">
                <button class="btn btn--primary btn--sm" type="submit"><?= icon('save', 14) ?> Simpan Permission</button>
                <span class="tiny muted">Perubahan dicatat pada audit trail.</span>
            </div>
        </form>
    </details>
<?php endforeach; ?>
