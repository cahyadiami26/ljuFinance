<?php
/**
 * Laporan laba rugi.
 *
 * @var array $laporan
 * @var array $rasio
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 * @var bool $canExport
 */
use Lju\Core\View;
use Lju\Services\ReportService;

$labaBersih = (float) ($laporan['laba_bersih'] ?? 0);
$margin = (float) ($laporan['margin'] ?? 0);
$labaKotor = (float) ($laporan['laba_kotor'] ?? 0);
$pendapatan = (float) ($laporan['pendapatan'] ?? 0);
$bebanTotal = (float) ($laporan['beban_total'] ?? 0);
?>
<?= View::partial('pages/report/_toolbar', [
    'action' => url('report/income'), 'route' => 'report/income', 'periodeList' => $periodeList, 'periodeId' => $periodeId,
    'mode' => $mode, 'filter' => $filter, 'filterList' => $filterList,
]) ?>

<?= View::partial('pages/report/_kpi', ['kpi' => $laporan['kpi']]) ?>

<?= View::partial('pages/report/_table', ['laporan' => $laporan]) ?>

<div class="grid-main mt-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Struktur Laba Rugi</h2>
                <div class="card-sub">Proporsi terhadap total pendapatan periode</div>
            </div>
        </div>
        <div class="card-body">
            <?php
            $komposisi = [
                ['label' => 'Beban Pokok', 'nilai' => max(0.0, $pendapatan - $labaKotor), 'tone' => 'warning'],
                ['label' => 'Beban Operasional & Lain', 'nilai' => max(0.0, $bebanTotal - max(0.0, $pendapatan - $labaKotor)), 'tone' => 'danger'],
                ['label' => 'Laba Bersih', 'nilai' => max(0.0, $labaBersih), 'tone' => 'success'],
            ];
            foreach ($komposisi as $item):
                $persen = $pendapatan > 0 ? ($item['nilai'] / $pendapatan) * 100 : 0;
                ?>
                <div style="margin-bottom:14px">
                    <div class="row" style="justify-content:space-between">
                        <span class="small strong"><?= e((string) $item['label']) ?></span>
                        <span class="small"><?= e(rupiah((float) $item['nilai'])) ?> · <?= e(number_format($persen, 1, ',', '.')) ?>%</span>
                    </div>
                    <div class="progress-track progress-track--<?= e((string) $item['tone']) ?>" style="margin-top:6px">
                        <span style="width:<?= max(1, min(100, (int) round($persen))) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">
                    Laba bersih periode ini <strong><?= e(rupiah($labaBersih)) ?></strong>
                    dengan margin <strong><?= e(ReportService::pctLabel($margin)) ?></strong>.
                    Total beban <?= e(rupiah($bebanTotal)) ?>.
                </div>
            </div>
        </div>
    </div>

    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rasio Terkait</h2>
                <div class="card-sub">Dari neraca &amp; laba rugi periode terpilih</div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Rasio</th><th style="width:120px">Nilai</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rasio as $item): ?>
                        <tr>
                            <td class="small strong"><?= e((string) $item['label']) ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $item['tone'] === 'up' ? 'sukses' : ((string) $item['tone'] === 'down' ? 'bahaya' : 'netral') ?>">
                                    <?= e(ReportService::formatNilai($item['nilai'], (string) $item['format'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
