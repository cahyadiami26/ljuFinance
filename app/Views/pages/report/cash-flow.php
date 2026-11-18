<?php
/**
 * Laporan arus kas (metode langsung + rekonsiliasi).
 *
 * @var array $laporan
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 */
use Lju\Core\View;

$selisih = (float) ($laporan['rekon_selisih'] ?? 0);
$neto = (float) ($laporan['neto'] ?? 0);
?>
<?= View::partial('pages/report/_toolbar', [
    'action' => url('report/cash-flow'), 'route' => 'report/cash-flow', 'periodeList' => $periodeList, 'periodeId' => $periodeId,
    'mode' => $mode, 'filter' => $filter, 'filterList' => $filterList,
]) ?>

<?= View::partial('pages/report/_kpi', ['kpi' => $laporan['kpi']]) ?>

<div class="notice notice--<?= abs($selisih) < 0.01 ? 'success' : 'warning' ?> mb-16">
    <?= icon(abs($selisih) < 0.01 ? 'check-circle' : 'alert', 16) ?>
    <div>
        <?php if (abs($selisih) < 0.01): ?>
            <strong>Arus kas terekonstruksi.</strong>
            Kas awal <?= e(rupiah((float) $laporan['kas_awal'])) ?> + neto <?= e(rupiah($neto)) ?> = kas akhir <?= e(rupiah((float) $laporan['kas_akhir'])) ?>.
        <?php else: ?>
            <strong>Ada selisih rekonsiliasi <?= e(rupiah($selisih)) ?>.</strong>
            Periksa jurnal yang memuat akun kas/bank dengan lawan bukan akun kas, atau jurnal tidak seimbang.
        <?php endif; ?>
    </div>
</div>

<?= View::partial('pages/report/_table', ['laporan' => $laporan]) ?>

<div class="card mt-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Metode &amp; Cakupan</h2>
            <div class="card-sub">Arus kas disusun dengan metode langsung dari mutasi akun kas dan bank</div>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <h3 class="small strong mb-8">Sumber data</h3>
                <p class="tiny muted">
                    Setiap jurnal posted yang menyentuh akun bertanda <em>kas/bank</em> dipecah menjadi arus kas masuk
                    dan keluar berdasarkan akun lawan. Akun lawan dikelompokkan menjadi aktivitas operasi,
                    investasi, dan pendanaan.
                </p>
            </div>
            <div>
                <h3 class="small strong mb-8">Rekonsiliasi</h3>
                <p class="tiny muted">
                    Saldo kas awal <?= e(rupiah((float) $laporan['kas_awal'])) ?>,
                    perubahan neto <?= e(rupiah($neto)) ?>,
                    dan saldo kas akhir <?= e(rupiah((float) $laporan['kas_akhir'])) ?>.
                    Selisih <?= e(rupiah($selisih)) ?> berasal dari jurnal tidak seimbang atau akun kas yang tidak bertanda kas/bank.
                </p>
            </div>
        </div>
        <div class="notice notice--info mt-12">
            <?= icon('info', 15) ?>
            <div class="tiny">Filter bidang/divisi/proyek memengaruhi baris operasi, tetapi rekonsiliasi kas selalu memakai seluruh jurnal posted.</div>
        </div>
    </div>
</div>
