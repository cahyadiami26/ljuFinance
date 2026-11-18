<?php
/**
 * Laba rugi per proyek.
 *
 * @var array $laporan
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 */
use Lju\Core\View;
use Lju\Services\ReportService;

$jumlahProyek = (int) ($laporan['jumlah_proyek'] ?? 0);
$margin = (float) ($laporan['margin'] ?? 0);
?>
<?= View::partial('pages/report/_toolbar', [
    'action' => url('report/project'), 'route' => 'report/project', 'periodeList' => $periodeList, 'periodeId' => $periodeId,
    'mode' => $mode, 'filter' => $filter, 'filterList' => $filterList,
]) ?>

<?= View::partial('pages/report/_kpi', ['kpi' => $laporan['kpi']]) ?>

<?= View::partial('pages/report/_table', ['laporan' => $laporan]) ?>

<div class="grid-2 mt-16">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Ringkasan Proyek</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Jumlah proyek</dt><dd><?= e((string) $jumlahProyek) ?> proyek</dd>
                <dt>Total pendapatan</dt><dd><?= e(rupiah((float) ($laporan['pendapatan'] ?? 0))) ?></dd>
                <dt>Total beban</dt><dd><?= e(rupiah((float) ($laporan['beban'] ?? 0))) ?></dd>
                <dt>Laba proyek</dt><dd class="strong"><?= e(rupiah((float) ($laporan['laba'] ?? 0))) ?></dd>
                <dt>Margin</dt>
                <dd class="<?= $margin >= 0 ? 'trend--up' : 'trend--down' ?>"><?= e(ReportService::pctLabel($margin)) ?></dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Cara Membaca</h2>
        </div>
        <div class="card-body">
            <p class="tiny muted">
                Pendapatan dan beban diambil dari dimensi proyek pada baris jurnal. Baris tanpa proyek
                dikelompokkan sebagai <em>Tanpa Proyek</em> agar total tetap sama dengan laba rugi periode.
                Proyek tanpa aktivitas tidak ditampilkan.
            </p>
            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">Gunakan filter proyek pada toolbar untuk melihat satu proyek secara rinci.</div>
            </div>
        </div>
    </div>
</div>
