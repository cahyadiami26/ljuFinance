<?php
/**
 * Kartu stok seluruh barang.
 *
 * @var array $movements
 * @var array $items
 * @var int $itemId
 * @var string $tipe
 * @var array $tipeList
 */
$totalMasuk = 0.0;
$totalKeluar = 0.0;
foreach ($movements as $row) {
    if ((string) $row['tipe'] === 'masuk') {
        $totalMasuk += (float) $row['nilai'];
    } elseif ((string) $row['tipe'] === 'keluar') {
        $totalKeluar += (float) $row['nilai'];
    }
}
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Transaksi Tampil</div>
        <div class="summary-value"><?= count($movements) ?></div>
        <div class="tiny muted">Maksimal 200 mutasi terakhir</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Masuk</div>
        <div class="summary-value"><?= e(rupiah_short($totalMasuk)) ?></div>
        <div class="tiny muted">Penerimaan barang</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Keluar</div>
        <div class="summary-value"><?= e(rupiah_short($totalKeluar)) ?></div>
        <div class="tiny muted">Pemakaian barang</div>
    </div>
</div>

<form class="toolbar mb-16" method="get" action="<?= e(url('inventory/movements')) ?>">
    <input type="hidden" name="r" value="inventory/movements">
    <select class="filter-select" name="item" data-autosubmit>
        <option value="">Semua barang</option>
        <?php foreach ($items as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $itemId === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['kode'] . ' — ' . $item['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="tipe" data-autosubmit>
        <option value="">Semua tipe</option>
        <?php foreach ($tipeList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <a class="btn" href="<?= e(url('inventory')) ?>"><?= icon('package', 15) ?> Daftar Barang</a>
</form>

<div class="card card--flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:140px">Nomor</th>
                    <th style="width:105px">Tanggal</th>
                    <th>Barang</th>
                    <th style="width:90px">Tipe</th>
                    <th class="num" style="width:100px">Qty</th>
                    <th class="num" style="width:130px">Nilai</th>
                    <th class="num" style="width:110px">Stok Akhir</th>
                    <th style="width:130px">Gudang</th>
                    <th style="width:140px">Jurnal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $gerak): ?>
                    <tr>
                        <td class="mono small"><?= e((string) $gerak['nomor']) ?></td>
                        <td class="small"><?= e(date('d M Y', strtotime((string) $gerak['tanggal']))) ?></td>
                        <td class="small"><a href="<?= e(url('inventory/item/' . (int) $gerak['item_id'])) ?>"><?= e((string) $gerak['item_kode'] . ' — ' . $gerak['item_nama']) ?></a></td>
                        <td><span class="badge badge--<?= (string) $gerak['tipe'] === 'masuk' ? 'sukses' : ((string) $gerak['tipe'] === 'keluar' ? 'warning' : 'netral') ?>"><?= e(ucfirst((string) $gerak['tipe'])) ?></span></td>
                        <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $gerak['qty'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $gerak['nilai'])) ?></td>
                        <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $gerak['stok_setelah'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="small"><?= e((string) ($gerak['warehouse_nama'] ?? '—')) ?></td>
                        <td class="small"><?= e((string) ($gerak['journal_nomor'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($movements === []): ?>
                    <tr><td colspan="9"><p class="muted small" style="padding:16px">Belum ada mutasi persediaan pada filter ini.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
