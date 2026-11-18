<?php
/**
 * Laporan perubahan ekuitas.
 *
 * @var array $laporan
 * @var array $periodeList
 * @var int $periodeId
 * @var string $mode
 * @var array $filter
 * @var array $filterList
 */
use Lju\Core\View;

$mutasi = (array) ($laporan['mutasi'] ?? []);
?>
<?= View::partial('pages/report/_toolbar', [
    'action' => url('report/equity'), 'route' => 'report/equity', 'periodeList' => $periodeList, 'periodeId' => $periodeId,
    'mode' => $mode, 'filter' => $filter, 'filterList' => $filterList,
]) ?>

<?= View::partial('pages/report/_kpi', ['kpi' => $laporan['kpi']]) ?>

<?= View::partial('pages/report/_table', ['laporan' => $laporan]) ?>

<div class="grid-2 mt-16">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Rincian Mutasi</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Saldo awal</dt><dd><?= e(rupiah((float) ($laporan['saldo_awal'] ?? 0))) ?></dd>
                <dt>Laba (rugi) periode</dt><dd><?= e(rupiah((float) ($laporan['laba_periode'] ?? 0))) ?></dd>
                <dt>Tambahan modal</dt><dd class="trend--up"><?= e(rupiah((float) ($laporan['tambahan'] ?? 0))) ?></dd>
                <dt>Pengurangan / prive</dt><dd class="trend--down"><?= e(rupiah((float) ($laporan['pengurangan'] ?? 0))) ?></dd>
                <dt>Total mutasi</dt><dd class="strong"><?= e(rupiah((float) ($laporan['total_mutasi'] ?? 0))) ?></dd>
                <dt>Saldo akhir</dt><dd class="strong"><?= e(rupiah((float) ($laporan['saldo_akhir'] ?? 0))) ?></dd>
            </dl>

            <?php if ($mutasi !== []): ?>
                <h3 class="small strong mt-16 mb-8">Mutasi per akun ekuitas</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Akun</th><th class="num">Awal</th><th class="num">Tambah</th><th class="num">Kurang</th><th class="num">Akhir</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mutasi as $item): ?>
                                <tr>
                                    <td class="small"><span class="rpt-kode"><?= e((string) $item['kode']) ?></span> <?= e((string) $item['nama']) ?></td>
                                    <td class="num"><?= e(rupiah((float) $item['awal'])) ?></td>
                                    <td class="num"><?= e(rupiah((float) $item['tambah'])) ?></td>
                                    <td class="num"><?= e(rupiah((float) $item['kurang'])) ?></td>
                                    <td class="num strong"><?= e(rupiah((float) $item['akhir'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Catatan Penyajian</h2>
        </div>
        <div class="card-body">
            <p class="tiny muted">
                Laporan perubahan ekuitas menyajikan saldo awal pemilik, laba atau rugi tahun berjalan,
                setoran modal, dan pengambilan pemilik (prive) selama periode. Laba tahun berjalan dihitung
                dari akun pendapatan dan beban yang belum ditutup ke akun laba ditahan.
            </p>
            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">Angka saldo akhir harus sama dengan baris ekuitas pada neraca tanggal yang sama.</div>
            </div>
        </div>
    </div>
</div>
