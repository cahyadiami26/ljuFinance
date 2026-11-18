<?php
/**
 * Strip KPI laporan.
 *
 * @var array $kpi
 */
use Lju\Services\ReportService;
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(<?= max(1, count($kpi)) ?>,minmax(0,1fr))">
    <?php foreach ($kpi as $item): ?>
        <?php $tone = (string) ($item['tone'] ?? 'netral'); ?>
        <div class="summary-item">
            <div class="summary-label"><?= e((string) $item['label']) ?></div>
            <div class="summary-value <?= $tone === 'up' ? 'summary-value--up' : ($tone === 'down' ? 'summary-value--down' : '') ?>">
                <?= e(ReportService::formatNilai($item['nilai'], (string) ($item['tipe'] ?? 'uang'))) ?>
            </div>
            <div class="tiny muted"><?= e((string) ($item['hint'] ?? '')) ?></div>
        </div>
    <?php endforeach; ?>
</div>
