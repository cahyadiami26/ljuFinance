<?php
/**
 * Filter periode/dimensi laporan.
 *
 * @var string $action
 * @var string $route
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 */
?>
<form class="toolbar mb-16" method="get" action="<?= e($action) ?>">
    <input type="hidden" name="r" value="<?= e($route) ?>">
    <select class="filter-select" name="periode" data-autosubmit>
        <?php foreach ($periodeList as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $periodeId === (int) $item['id'] ? 'selected' : '' ?>>
                <?= e((string) $item['nama']) ?> · <?= e((string) $item['status']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" name="mode" data-autosubmit>
        <option value="bulan" <?= $mode === 'bulan' ? 'selected' : '' ?>>Bulanan</option>
        <option value="ytd" <?= $mode === 'ytd' ? 'selected' : '' ?>>s.d. tahun berjalan</option>
    </select>

    <select class="filter-select" name="bidang" data-autosubmit>
        <option value="">Semua bidang</option>
        <?php foreach ($filterList['bidang'] as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($filter['bidang_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" name="divisi" data-autosubmit>
        <option value="">Semua divisi</option>
        <?php foreach ($filterList['divisi'] as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($filter['divisi_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" name="unit" data-autosubmit>
        <option value="">Semua unit usaha</option>
        <?php foreach ($filterList['business_unit'] as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($filter['business_unit_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" name="project" data-autosubmit>
        <option value="">Semua proyek</option>
        <?php foreach ($filterList['project'] as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($filter['project_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
        <?php endforeach; ?>
    </select>

    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
</form>
