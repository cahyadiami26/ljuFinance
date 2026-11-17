<?php
/**
 * Neraca saldo.
 *
 * @var array $rows
 * @var float $totalDebit
 * @var float $totalKredit
 * @var float $totalAwal
 * @var float $selisih
 * @var bool $seimbang
 * @var array $rekap
 * @var array $periode
 * @var int $periodeId
 * @var array $periods
 * @var string $mode
 * @var string $tipe
 * @var string $from
 * @var string $to
 * @var array $tipeList
 */
$dk = static function (float $value): string {
    if (abs($value) < 0.005) {
        return '<span class="muted">—</span>';
    }
    return '<span class="num-strong">' . e(rupiah(abs($value))) . '</span> <span class="dk-tag">' . ($value > 0 ? 'D' : 'K') . '</span>';
};
$rekapLabels = ['aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'ekuitas' => 'Ekuitas', 'pendapatan' => 'Pendapatan', 'beban' => 'Beban'];
?>
<form class="toolbar" method="get" action="<?= e(url('trial-balance')) ?>">
    <input type="hidden" name="r" value="trial-balance">
    <select class="filter-select" name="periode" data-autosubmit>
        <?php foreach ($periods as $period): ?>
            <option value="<?= (int) $period['id'] ?>" <?= $periodeId === (int) $period['id'] ? 'selected' : '' ?>>
                <?= e((string) $period['nama']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="mode" data-autosubmit>
        <option value="periode" <?= $mode === 'periode' ? 'selected' : '' ?>>Mutasi periode</option>
        <option value="kumulatif" <?= $mode === 'kumulatif' ? 'selected' : '' ?>>Kumulatif tahun buku</option>
    </select>
    <select class="filter-select" name="tipe" data-autosubmit>
        <option value="">Semua tipe akun</option>
        <?php foreach ($tipeList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="pill"><span class="mono"><?= e(tanggal($from, 'd M Y')) ?> – <?= e(tanggal($to, 'd M Y')) ?></span></span>
    <span class="spacer"></span>
    <?php if ($seimbang): ?>
        <span class="badge badge--sukses"><?= icon('check', 12) ?> Neraca seimbang</span>
    <?php else: ?>
        <span class="badge badge--bahaya">Selisih <?= e(rupiah(abs($selisih))) ?></span>
    <?php endif; ?>
</form>

<div class="summary-strip" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Saldo Awal</div>
        <div class="summary-value"><?= $dk($totalAwal) ?></div>
        <div class="tiny muted">Posisi sebelum <?= e(tanggal($from, 'd M Y')) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Total Debit</div>
        <div class="summary-value"><?= e(rupiah($totalDebit)) ?></div>
        <div class="tiny muted">Saldo akhir sisi debit</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Total Kredit</div>
        <div class="summary-value"><?= e(rupiah($totalKredit)) ?></div>
        <div class="tiny muted">Saldo akhir sisi kredit</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Status</div>
        <div class="summary-value"><?= $seimbang ? badge('Seimbang', 'sukses') : badge('Tidak seimbang', 'bahaya') ?></div>
        <div class="tiny muted"><?= count($rows) ?> akun bermutasi</div>
    </div>
</div>

<?php if (!$seimbang): ?>
    <div class="notice notice--warning mb-16">
        <?= icon('alert', 16) ?>
        <div>
            <strong>Neraca tidak seimbang (selisih <?= e(rupiah(abs($selisih))) ?>).</strong>
            Periksa jurnal yang tidak seimbang, jurnal tanpa pasangan, atau filter dimensi yang tidak berpasangan.
        </div>
    </div>
<?php endif; ?>

<?php if ($rekap !== []): ?>
    <div class="row wrap mb-16" style="gap:8px">
        <?php foreach ($rekapLabels as $key => $label): ?>
            <?php if (isset($rekap[$key])): ?>
                <span class="pill">
                    <?= e($label) ?>: <strong style="margin-left:4px"><?= e(rupiah(abs($rekap[$key]))) ?></strong>
                    <span class="dk-tag"><?= $rekap[$key] >= 0 ? 'D' : 'K' ?></span>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('scale', 22) ?></div>
            <h3>Belum ada saldo</h3>
            <p>Belum ada jurnal posted pada periode ini. Neraca saldo akan terisi otomatis setelah jurnal diposting.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:100px">Kode</th>
                        <th>Nama Akun</th>
                        <th style="width:100px">Tipe</th>
                        <th class="num" style="width:150px">Saldo Awal</th>
                        <th class="num" style="width:140px">Mutasi Debit</th>
                        <th class="num" style="width:140px">Mutasi Kredit</th>
                        <th class="num" style="width:150px">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $row['kode']) ?></td>
                            <td>
                                <a class="small strong" href="<?= e(url('ledger', ['coa' => (int) $row['id'], 'from' => $from, 'to' => $to])) ?>">
                                    <?= e((string) $row['nama']) ?>
                                </a>
                            </td>
                            <td class="tiny muted"><?= e($tipeList[(string) $row['tipe']] ?? ucfirst((string) $row['tipe'])) ?></td>
                            <td class="num small"><?= $dk((float) $row['awal']) ?></td>
                            <td class="num small"><?= (float) $row['mutasi_debit'] > 0 ? e(rupiah($row['mutasi_debit'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num small"><?= (float) $row['mutasi_kredit'] > 0 ? e(rupiah($row['mutasi_kredit'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num small"><?= $dk((float) $row['akhir']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="right strong small">TOTAL</td>
                        <td class="num strong"><?= e(rupiah(abs($totalAwal))) ?></td>
                        <td class="num strong"><?= e(rupiah(array_sum(array_column($rows, 'mutasi_debit')))) ?></td>
                        <td class="num strong"><?= e(rupiah(array_sum(array_column($rows, 'mutasi_kredit')))) ?></td>
                        <td class="num strong"><?= e(rupiah($totalDebit)) ?> / <?= e(rupiah($totalKredit)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="list-foot">
            <span>Nilai akhir ditampilkan per sisi (D = debit, K = kredit). Total kolom saldo akhir: <?= e(rupiah($totalDebit)) ?> debit vs <?= e(rupiah($totalKredit)) ?> kredit.</span>
        </div>
    <?php endif; ?>
</div>
