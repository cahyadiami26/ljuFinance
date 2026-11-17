<?php
/**
 * Audit trail.
 *
 * @var array $rows
 * @var array $modules
 * @var array $actions
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $module
 * @var string $action
 * @var string $from
 * @var string $to
 * @var int $total
 */
$actionVariant = [
    'create' => 'sukses', 'update' => 'warning', 'delete' => 'bahaya', 'approve' => 'sukses',
    'reject' => 'bahaya', 'post' => 'info', 'reverse' => 'bahaya', 'close' => 'warning',
    'login' => 'netral', 'export' => 'primary', 'refresh' => 'netral',
];
?>
<form class="toolbar" method="get" action="<?= e(url('system/audit')) ?>">
    <input type="hidden" name="r" value="system/audit">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari deskripsi, nomor dokumen, pengguna…">
    </div>
    <select class="filter-select" name="module">
        <option value="">Semua modul</option>
        <?php foreach ($modules as $item): ?>
            <option value="<?= e((string) $item) ?>" <?= $module === (string) $item ? 'selected' : '' ?>><?= e((string) $item) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="action">
        <option value="">Semua aksi</option>
        <?php foreach ($actions as $item): ?>
            <option value="<?= e((string) $item) ?>" <?= $action === (string) $item ? 'selected' : '' ?>><?= e(ucfirst((string) $item)) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="input" type="date" name="from" value="<?= e($from) ?>" style="width:150px;height:38px">
    <input class="input" type="date" name="to" value="<?= e($to) ?>" style="width:150px;height:38px">
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $total ?> catatan</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('history', 22) ?></div>
            <h3>Tidak ada catatan audit</h3>
            <p>Sesuaikan filter untuk melihat aktivitas lain.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Waktu</th>
                        <th style="width:150px">Pengguna</th>
                        <th style="width:100px">Aksi</th>
                        <th style="width:130px">Modul</th>
                        <th>Deskripsi</th>
                        <th style="width:90px">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="strong small" style="font-size:12px"><?= e(tanggal_waktu($row['created_at'], 'd M Y')) ?></div>
                                <div class="tiny muted"><?= e(tanggal_waktu($row['created_at'], 'H:i:s')) ?> · <?= e(waktu_relatif($row['created_at'])) ?></div>
                            </td>
                            <td class="small"><?= e((string) $row['user_name']) ?></td>
                            <td><?= badge(ucfirst((string) $row['action']), $actionVariant[(string) $row['action']] ?? 'netral') ?></td>
                            <td class="small muted"><?= e((string) $row['module']) ?></td>
                            <td>
                                <div class="small"><?= e((string) ($row['description'] ?? '—')) ?></div>
                                <?php if (!empty($row['ref_code'])): ?>
                                    <div class="tiny"><span class="code-pill"><?= e((string) $row['ref_code']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($row['old_values']) || !empty($row['new_values'])): ?>
                                    <details class="tiny mt-8">
                                        <summary class="muted" style="cursor:pointer">Lihat detail perubahan</summary>
                                        <pre style="white-space:pre-wrap;background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:10px;margin-top:6px;font-size:10.5px;line-height:1.5"><?= e((string) ($row['old_values'] ?? 'null')) ?>

<?= e((string) ($row['new_values'] ?? 'null')) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="tiny muted"><?= e((string) ($row['ip_address'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> catatan</span>
            <?= $paginator->links('system/audit', array_filter(['q' => $q, 'module' => $module, 'action' => $action, 'from' => $from, 'to' => $to])) ?>
        </div>
    <?php endif; ?>
</div>
