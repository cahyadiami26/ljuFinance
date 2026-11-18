<?php
/**
 * Daftar barang persediaan.
 *
 * @var array $items
 * @var string $q
 * @var string $kategori
 * @var array $kategoriList
 * @var array $ringkasan
 * @var float $nilaiTampil
 * @var array $warehouses
 * @var array $gerakTerakhir
 * @var bool $canManage
 */
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Jenis Barang</div>
        <div class="summary-value"><?= (int) ($ringkasan['jumlah_item'] ?? 0) ?></div>
        <div class="tiny muted"><?= (int) ($ringkasan['gudang'] ?? 0) ?> gudang aktif</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Persediaan</div>
        <div class="summary-value"><?= e(rupiah_short((float) ($ringkasan['nilai_stok'] ?? 0))) ?></div>
        <div class="tiny muted">Rata-rata bergerak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Di Bawah Minimum</div>
        <div class="summary-value <?= (int) ($ringkasan['stok_minimum'] ?? 0) > 0 ? 'summary-value--down' : '' ?>"><?= (int) ($ringkasan['stok_minimum'] ?? 0) ?></div>
        <div class="tiny muted">Perlu pengadaan ulang</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Mutasi Bulan Ini</div>
        <div class="summary-value"><?= (int) ($ringkasan['mutasi_bulan'] ?? 0) ?></div>
        <div class="tiny muted">Penerimaan &amp; pengeluaran</div>
    </div>
</div>

<form class="toolbar mb-16" method="get" action="<?= e(url('inventory')) ?>">
    <input type="hidden" name="r" value="inventory">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama barang…">
    </div>
    <select class="filter-select" name="kategori" data-autosubmit>
        <option value="">Semua kategori</option>
        <?php foreach ($kategoriList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $kategori === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= count($items) ?> barang · <?= e(rupiah($nilaiTampil)) ?></span>
</form>

<div class="card card--flush">
    <?php if ($items === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('package', 22) ?></div>
            <h3>Belum ada barang persediaan</h3>
            <p>Tambahkan barang agar penerimaan PO dan pengeluaran gudang tercatat.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('inventory/item/create')) ?>"><?= icon('plus', 15) ?> Barang Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:120px">Kode</th>
                        <th>Nama Barang</th>
                        <th style="width:120px">Kategori</th>
                        <th class="num" style="width:110px">Stok</th>
                        <th class="num" style="width:140px">Harga Rata-rata</th>
                        <th class="num" style="width:140px">Nilai Stok</th>
                        <th class="num" style="width:90px">Minimum</th>
                        <th style="width:90px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $rendah = (float) $item['stok_min'] > 0 && (float) $item['stok'] <= (float) $item['stok_min']; ?>
                        <tr>
                            <td><a class="mono strong" href="<?= e(url('inventory/item/' . (int) $item['id'])) ?>"><?= e((string) $item['kode']) ?></a></td>
                            <td>
                                <a class="small strong" href="<?= e(url('inventory/item/' . (int) $item['id'])) ?>"><?= e((string) $item['nama']) ?></a>
                                <div class="tiny muted"><?= e((string) ($item['coa_persediaan_kode'] ?? '')) ?> · <?= e((string) $item['satuan']) ?></div>
                            </td>
                            <td class="small"><?= e($kategoriList[(string) $item['kategori']] ?? (string) $item['kategori']) ?></td>
                            <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $item['stok'], 2, ',', '.'), '0'), ',')) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $item['harga_rata'])) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $item['nilai_stok'])) ?></td>
                            <td class="num small"><?= e(rtrim(rtrim(number_format((float) $item['stok_min'], 2, ',', '.'), '0'), ',')) ?></td>
                            <td>
                                <?php if ((int) $item['is_active'] !== 1): ?>
                                    <span class="badge badge--netral">Nonaktif</span>
                                <?php elseif ($rendah): ?>
                                    <span class="badge badge--warning">Rendah</span>
                                <?php else: ?>
                                    <span class="badge badge--sukses">Aman</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($gerakTerakhir !== []): ?>
    <div class="card card--flush mt-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Mutasi Terakhir</h2>
                <div class="card-sub">Delapan transaksi persediaan terbaru</div>
            </div>
            <a class="btn btn--sm" href="<?= e(url('inventory/movements')) ?>">Semua Kartu Stok</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:140px">Nomor</th>
                        <th style="width:110px">Tanggal</th>
                        <th>Barang</th>
                        <th style="width:90px">Tipe</th>
                        <th class="num" style="width:100px">Qty</th>
                        <th class="num" style="width:140px">Nilai</th>
                        <th style="width:150px">Jurnal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gerakTerakhir as $gerak): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $gerak['nomor']) ?></td>
                            <td class="small"><?= e(date('d M Y', strtotime((string) $gerak['tanggal']))) ?></td>
                            <td class="small"><a href="<?= e(url('inventory/item/' . (int) $gerak['item_id'])) ?>"><?= e((string) $gerak['item_kode'] . ' — ' . $gerak['item_nama']) ?></a></td>
                            <td><span class="badge badge--<?= (string) $gerak['tipe'] === 'masuk' ? 'sukses' : ((string) $gerak['tipe'] === 'keluar' ? 'warning' : 'netral') ?>"><?= e(ucfirst((string) $gerak['tipe'])) ?></span></td>
                            <td class="num mono"><?= e((string) (float) $gerak['qty']) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $gerak['nilai'])) ?></td>
                            <td class="small"><?= e((string) ($gerak['journal_nomor'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
