<?php
/**
 * Detail barang & kartu stok.
 *
 * @var array $item
 * @var array $movements
 * @var float $masuk
 * @var float $keluar
 * @var float $nilaiStok
 * @var bool $canManage
 */
$stok = (float) $item['stok'];
$minimum = (float) $item['stok_min'];
$rendah = $minimum > 0 && $stok <= $minimum;
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Stok Saat Ini</div>
        <div class="summary-value <?= $rendah ? 'summary-value--down' : '' ?>">
            <?= e(rtrim(rtrim(number_format($stok, 2, ',', '.'), '0'), ',')) ?> <span class="small muted"><?= e((string) $item['satuan']) ?></span>
        </div>
        <div class="tiny muted">Minimum <?= e(rtrim(rtrim(number_format($minimum, 2, ',', '.'), '0'), ',')) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Harga Rata-rata</div>
        <div class="summary-value"><?= e(rupiah((float) $item['harga_rata'])) ?></div>
        <div class="tiny muted">Rata-rata bergerak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Persediaan</div>
        <div class="summary-value"><?= e(rupiah_short($nilaiStok)) ?></div>
        <div class="tiny muted">Stok × harga rata-rata</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Mutasi</div>
        <div class="summary-value"><?= count($movements) ?></div>
        <div class="tiny muted">Masuk <?= e(rtrim(rtrim(number_format($masuk, 2, ',', '.'), '0'), ',')) ?> · Keluar <?= e(rtrim(rtrim(number_format($keluar, 2, ',', '.'), '0'), ',')) ?></div>
    </div>
</div>

<div class="grid-main mb-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Barang</h2>
                <div class="card-sub"><?= e((string) $item['nama']) ?></div>
            </div>
            <span class="badge badge--<?= (int) $item['is_active'] === 1 ? 'sukses' : 'netral' ?>"><?= (int) $item['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <div class="field-label">Kode</div>
                    <div class="mono strong small"><?= e((string) $item['kode']) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Kategori</div>
                    <div class="small"><?= e((string) $item['kategori']) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Akun Persediaan</div>
                    <div class="small"><?= e((string) ($item['coa_persediaan_kode'] ?? '—') . ' — ' . (string) ($item['coa_persediaan_nama'] ?? '')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Akun Beban</div>
                    <div class="small"><?= e((string) ($item['coa_beban_kode'] ?? '—') . ' — ' . (string) ($item['coa_beban_nama'] ?? '')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Aksi Cepat</h2>
                <div class="card-sub">Mutasi manual untuk koreksi gudang</div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$canManage): ?>
                <p class="muted small">Anda tidak memiliki hak untuk mencatat mutasi persediaan.</p>
            <?php else: ?>
                <a class="btn btn--block mb-8" href="<?= e(url('inventory/item/' . (int) $item['id'] . '/masuk')) ?>"><?= icon('arrow-down', 15) ?> Catat Barang Masuk</a>
                <a class="btn btn--primary btn--block" href="<?= e(url('inventory/item/' . (int) $item['id'] . '/keluar')) ?>"><?= icon('arrow-up', 15) ?> Catat Barang Keluar</a>
                <div class="notice notice--info mt-12">
                    <?= icon('info', 15) ?>
                    <div class="tiny">Pengeluaran membentuk jurnal Dr beban / Cr persediaan pada periode tanggal transaksi.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card card--flush">
    <div class="card-head">
        <div>
            <h2 class="card-title">Kartu Stok</h2>
            <div class="card-sub">Saldo stok tercatat setelah setiap mutasi</div>
        </div>
        <span class="pill mono"><?= e((string) $item['kode']) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:140px">Nomor</th>
                    <th style="width:105px">Tanggal</th>
                    <th style="width:90px">Tipe</th>
                    <th>Keterangan</th>
                    <th class="num" style="width:90px">Qty</th>
                    <th class="num" style="width:130px">Harga</th>
                    <th class="num" style="width:140px">Nilai</th>
                    <th class="num" style="width:100px">Stok</th>
                    <th style="width:140px">Jurnal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $gerak): ?>
                    <tr>
                        <td class="mono small"><?= e((string) $gerak['nomor']) ?></td>
                        <td class="small"><?= e(date('d M Y', strtotime((string) $gerak['tanggal']))) ?></td>
                        <td><span class="badge badge--<?= (string) $gerak['tipe'] === 'masuk' ? 'sukses' : ((string) $gerak['tipe'] === 'keluar' ? 'warning' : 'netral') ?>"><?= e(ucfirst((string) $gerak['tipe'])) ?></span></td>
                        <td class="small">
                            <?= e((string) ($gerak['keterangan'] ?? '—')) ?>
                            <?php if (!empty($gerak['warehouse_nama'])): ?>
                                <div class="tiny muted"><?= e((string) $gerak['warehouse_nama']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $gerak['qty'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $gerak['harga'])) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $gerak['nilai'])) ?></td>
                        <td class="num mono strong"><?= e(rtrim(rtrim(number_format((float) $gerak['stok_setelah'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="small"><?= e((string) ($gerak['journal_nomor'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($movements === []): ?>
                    <tr><td colspan="9"><p class="muted small" style="padding:16px">Belum ada mutasi. Stok akan muncul setelah penerimaan barang atau penerimaan PO.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
