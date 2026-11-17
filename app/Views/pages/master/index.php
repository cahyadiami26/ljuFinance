<?php
/**
 * Daftar master data generik.
 *
 * @var array $config
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var bool $canManage
 * @var string $resourceKey
 */

$labelMap = [
    'cost'      => ['Cost Center', 'netral'],
    'profit'    => ['Profit Center', 'sukses'],
    'investasi' => ['Pusat Investasi', 'info'],
    'vendor'    => ['Vendor', 'netral'],
    'customer'  => ['Pelanggan', 'info'],
    'both'      => ['Vendor & Pelanggan', 'primary'],
];

$renderCell = static function (array $row, array $column) use ($labelMap): string {
    $key = (string) $column[0];
    $type = (string) ($column[2] ?? 'text');
    $value = $row[$key] ?? null;

    switch ($type) {
        case 'code':
            return $value !== null && $value !== '' ? '<span class="code-pill">' . e((string) $value) . '</span>' : '<span class="muted">-</span>';
        case 'active':
            return ((int) $value === 1) ? badge('Aktif', 'sukses') : badge('Nonaktif', 'netral');
        case 'money':
            return '<span class="num strong">' . e(rupiah((float) $value)) . '</span>';
        case 'date':
            return e(tanggal($value));
        case 'number':
            return '<span class="num">' . e(angka((float) $value)) . '</span>';
        case 'badge':
            $mapped = $labelMap[(string) $value] ?? [ucfirst((string) $value), 'netral'];
            return badge($mapped[0], $mapped[1]);
        default:
            if ($value === null || $value === '') {
                return '<span class="muted">—</span>';
            }
            return e((string) $value);
    }
};
?>
<div class="toolbar">
    <form class="search-box" method="get" action="<?= e(url((string) $config['route'])) ?>">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari <?= e(strtolower((string) $config['title'])) ?>…">
        <input type="hidden" name="r" value="<?= e((string) $config['route']) ?>">
    </form>
    <span class="spacer"></span>
    <?php if ($canManage): ?>
        <a class="btn btn--primary" href="<?= e(url((string) $config['route'] . '/create')) ?>">
            <?= icon('plus', 16) ?> Tambah <?= e((string) $config['singular']) ?>
        </a>
    <?php endif; ?>
</div>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('database', 22) ?></div>
            <h3>Belum ada data <?= e(strtolower((string) $config['title'])) ?></h3>
            <p><?= $q !== '' ? 'Tidak ada hasil untuk pencarian "' . e($q) . '".' : 'Tambahkan data pertama untuk memulai.' ?></p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary btn--sm" href="<?= e(url((string) $config['route'] . '/create')) ?>">
                    <?= icon('plus', 14) ?> Tambah <?= e((string) $config['singular']) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach ($config['columns'] as $column): ?>
                            <th><?= e((string) $column[1]) ?></th>
                        <?php endforeach; ?>
                        <?php if ($canManage): ?>
                            <th class="right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($config['columns'] as $column): ?>
                                <td><?= $renderCell($row, $column) ?></td>
                            <?php endforeach; ?>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn--sm" href="<?= e(url((string) $config['route'] . '/' . (int) $row['id'] . '/edit')) ?>">
                                            <?= icon('edit', 14) ?> Ubah
                                        </a>
                                        <form method="post" action="<?= e(url((string) $config['route'] . '/' . (int) $row['id'] . '/toggle')) ?>"
                                              data-confirm="<?= ((int) $row['is_active'] === 1) ? 'Nonaktifkan' : 'Aktifkan' ?> data ini?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--sm <?= ((int) $row['is_active'] === 1) ? '' : 'btn--ghost' ?>" type="submit">
                                                <?= ((int) $row['is_active'] === 1) ? icon('power', 14) . ' Nonaktifkan' : icon('check', 14) . ' Aktifkan' ?>
                                            </button>
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
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> data</span>
            <?= $paginator->links((string) $config['route'], $q !== '' ? ['q' => $q] : []) ?>
        </div>
    <?php endif; ?>
</div>
