<?php
/**
 * Kinerja unit usaha: konsolidasi pendapatan, beban, laba, dan posisi keuangan.
 *
 * @var array $laporan
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 * @var bool $canExport
 */
use Lju\Core\View;
use Lju\Services\ReportService;

$units = (array) ($laporan['baris_unit'] ?? []);
$totalPendapatan = (float) ($laporan['total_pendapatan'] ?? 0);
$laba = (float) ($laporan['laba'] ?? 0);
$margin = $totalPendapatan > 0 ? ($laba / $totalPendapatan) * 100 : 0.0;
$teratas = array_slice($units, 0, 6);
?>
<?= View::partial('pages/report/_toolbar', [
    'action' => url('report/unit-usaha'), 'route' => 'report/unit-usaha', 'periodeList' => $periodeList, 'periodeId' => $periodeId,
    'mode' => $mode, 'filter' => $filter, 'filterList' => $filterList,
]) ?>

<?= View::partial('pages/report/_kpi', ['kpi' => $laporan['kpi']]) ?>

<?= View::partial('pages/report/_table', ['laporan' => $laporan]) ?>

<div class="grid-main mt-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Kontribusi Pendapatan</h2>
                <div class="card-sub">Porsi tiap unit terhadap pendapatan konsolidasi</div>
            </div>
            <span class="badge badge--netral"><?= e(ReportService::pctLabel($margin)) ?> margin</span>
        </div>
        <div class="card-body">
            <?php if ($teratas === []): ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('chart', 26) ?></div>
                    <div class="empty-title">Belum ada pendapatan tercatat</div>
                    <p class="empty-text">Tandai unit usaha pada baris jurnal agar kinerja tiap unit dapat dipantau.</p>
                </div>
            <?php else: ?>
                <?php foreach ($teratas as $unit): ?>
                    <?php
                    $persen = (float) ($unit['kontribusi'] ?? 0);
                    $unitLaba = (float) ($unit['laba'] ?? 0);
                    $tone = $unitLaba >= 0 ? 'success' : 'danger';
                    ?>
                    <div style="margin-bottom:14px">
                        <div class="row" style="justify-content:space-between">
                            <span class="small strong"><?= e((string) $unit['unit']) ?></span>
                            <span class="small"><?= e(rupiah((float) $unit['pendapatan'])) ?> · <?= e(number_format($persen, 1, ',', '.')) ?>%</span>
                        </div>
                        <div class="progress-track progress-track--<?= e($tone) ?>" style="margin-top:6px">
                            <span style="width:<?= max(1, min(100, (int) round($persen))) ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="notice notice--info mt-12">
                    <?= icon('info', 15) ?>
                    <div class="tiny">
                        Total pendapatan konsolidasi <strong><?= e(rupiah($totalPendapatan)) ?></strong>,
                        laba <strong><?= e(rupiah($laba)) ?></strong>.
                        Rincian lengkap tiap unit tersedia pada tabel di atas.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Catatan Penyusunan</h2>
                <div class="card-sub">Dasar perhitungan laporan unit usaha</div>
            </div>
        </div>
        <div class="card-body">
            <ul style="list-style:none;margin:0;padding:0">
                <?php foreach ((array) $laporan['catatan'] as $catatan): ?>
                    <li class="small" style="padding:8px 0;border-bottom:1px solid var(--border)">
                        <?= icon('info', 14) ?> <?= e((string) $catatan) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="notice notice--warning mt-12">
                <?= icon('alert', 15) ?>
                <div class="tiny">
                    Laporan ini belum melakukan eliminasi transaksi antar-unit. Gunakan filter unit
                    untuk meninjau satu unit usaha secara terpisah.
                </div>
            </div>
        </div>
    </div>
</div>
