<?php
/**
 * Neraca.
 *
 * @var array $laporan
 * @var array $rasio
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 */
use Lju\Core\View;

$seimbang = (bool) ($laporan['seimbang'] ?? true);
$selisih = (float) ($laporan['selisih'] ?? 0);
?>
<?= View::partial('pages/report/_toolbar', [
    'action' => url('report/balance-sheet'), 'route' => 'report/balance-sheet', 'periodeList' => $periodeList, 'periodeId' => $periodeId,
    'mode' => $mode, 'filter' => $filter, 'filterList' => $filterList,
]) ?>

<?= View::partial('pages/report/_kpi', ['kpi' => $laporan['kpi']]) ?>

<?php if (!$seimbang): ?>
    <div class="notice notice--warning mb-16">
        <?= icon('alert', 16) ?>
        <div>
            <strong>Neraca belum seimbang.</strong>
            Selisih <?= e(rupiah($selisih)) ?> antara total aset dan total kewajiban + ekuitas.
            Periksa jurnal posted atau filter dimensi yang dipakai.
        </div>
    </div>
<?php else: ?>
    <div class="notice notice--success mb-16">
        <?= icon('check-circle', 16) ?>
        <div><strong>Neraca seimbang.</strong> Total aset sama dengan total kewajiban dan ekuitas pada tanggal laporan.</div>
    </div>
<?php endif; ?>

<?= View::partial('pages/report/_table', ['laporan' => $laporan]) ?>

<div class="grid-main mt-16">
    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rasio Likuiditas &amp; Solvabilitas</h2>
                <div class="card-sub">Per <?= e(tanggal($laporan['rentang']['to'], 'd M Y')) ?></div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Rasio</th><th style="width:110px">Nilai</th><th>Keterangan</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rasio as $item): ?>
                        <tr>
                            <td class="small strong"><?= e((string) $item['label']) ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $item['tone'] === 'up' ? 'sukses' : ((string) $item['tone'] === 'down' ? 'bahaya' : 'netral') ?>">
                                    <?= e(\Lju\Services\ReportService::formatNilai($item['nilai'], (string) $item['format'])) ?>
                                </span>
                            </td>
                            <td class="tiny muted"><?= e((string) $item['hint']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Ringkasan Posisi</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Total Aset</dt><dd class="strong"><?= e(rupiah((float) $laporan['total_aset'])) ?></dd>
                <dt>Total Kewajiban</dt><dd><?= e(rupiah((float) $laporan['total_kewajiban'])) ?></dd>
                <dt>Total Ekuitas</dt><dd><?= e(rupiah((float) $laporan['total_ekuitas'])) ?></dd>
                <dt>Selisih</dt>
                <dd class="<?= $seimbang ? 'trend--up' : 'trend--down' ?>"><?= e(rupiah($selisih)) ?></dd>
                <dt>Rasio Lancar</dt>
                <dd><?= $laporan['rasio_lancar'] === null ? '—' : e(number_format((float) $laporan['rasio_lancar'], 2, ',', '.')) . '×' ?></dd>
            </dl>
            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">Aset lancar <?= e(rupiah((float) $laporan['aset_lancar'])) ?> dan kewajiban lancar <?= e(rupiah((float) $laporan['kewajiban_lancar'])) ?>.</div>
            </div>
        </div>
    </div>
</div>
