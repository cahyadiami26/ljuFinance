<?php
/**
 * Form pengeluaran barang.
 *
 * @var array $item
 * @var array $warehouses
 * @var array $projects
 * @var array $businessUnits
 * @var array $bidang
 * @var array $divisi
 * @var array $defaults
 * @var float $nilaiStok
 * @var string $bebanKode
 */
$stok = (float) $item['stok'];
$harga = (float) $item['harga_rata'];
?>
<form method="post" action="<?= e(url('inventory/item/' . (int) $item['id'] . '/keluar')) ?>">
    <?= csrf_field() ?>
    <div class="grid-main mb-16">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Pengeluaran Barang</h2>
                    <div class="card-sub">Jurnal Dr beban / Cr persediaan dibentuk otomatis.</div>
                </div>
                <span class="pill mono"><?= e((string) $item['kode']) ?></span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="k-tanggal">Tanggal</label>
                        <input class="input" type="date" id="k-tanggal" name="tanggal" value="<?= e((string) $defaults['tanggal']) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="k-gudang">Gudang</label>
                        <select class="select" id="k-gudang" name="warehouse_id">
                            <option value="">— tanpa gudang —</option>
                            <?php foreach ($warehouses as $gudang): ?>
                                <option value="<?= (int) $gudang['id'] ?>"><?= e((string) $gudang['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="k-qty">Qty Keluar (<?= e((string) $item['satuan']) ?>)</label>
                        <input class="input" type="number" step="0.0001" min="0.0001" max="<?= e((string) $stok) ?>" id="k-qty" name="qty"
                               value="<?= e((string) $defaults['qty']) ?>" required data-max-stok="<?= e((string) $stok) ?>">
                        <div class="tiny muted">Stok tersedia <?= e(number_format($stok, 2, ',', '.')) ?> <?= e((string) $item['satuan']) ?>.</div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="k-project">Proyek</label>
                        <select class="select" id="k-project" name="project_id">
                            <option value="">— bukan untuk proyek —</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= (int) $project['id'] ?>"><?= e($project['kode'] . ' — ' . $project['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="k-unit">Unit Usaha</label>
                        <select class="select" id="k-unit" name="business_unit_id">
                            <option value="">— tanpa unit —</option>
                            <?php foreach ($businessUnits as $unit): ?>
                                <option value="<?= (int) $unit['id'] ?>"><?= e((string) $unit['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="k-bidang">Bidang</label>
                        <select class="select" id="k-bidang" name="bidang_id">
                            <option value="">— tanpa bidang —</option>
                            <?php foreach ($bidang as $row): ?>
                                <option value="<?= (int) $row['id'] ?>"><?= e((string) $row['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="k-divisi">Divisi</label>
                        <select class="select" id="k-divisi" name="divisi_id">
                            <option value="">— tanpa divisi —</option>
                            <?php foreach ($divisi as $row): ?>
                                <option value="<?= (int) $row['id'] ?>"><?= e((string) $row['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field field--full">
                        <label class="field-label" for="k-ket">Keterangan</label>
                        <input class="input" type="text" id="k-ket" name="keterangan" maxlength="255" placeholder="Pemakaian untuk pekerjaan …">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Perkiraan Nilai</h2>
                    <div class="card-sub">Berdasarkan harga rata-rata saat ini</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="small muted">Stok tersedia</span>
                    <span class="mono strong"><?= e(number_format($stok, 2, ',', '.')) ?> <?= e((string) $item['satuan']) ?></span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="small muted">Harga rata-rata</span>
                    <span class="mono strong"><?= e(rupiah($harga)) ?></span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0">
                    <span class="small muted">Nilai persediaan</span>
                    <span class="mono strong"><?= e(rupiah($nilaiStok)) ?></span>
                </div>
                <div class="notice notice--warning mt-12">
                    <?= icon('alert', 15) ?>
                    <div class="tiny">
                        <?php if ($bebanKode === ''): ?>
                            Barang ini belum memiliki akun beban; pengeluaran akan ditolak sampai akun dilengkapi.
                        <?php else: ?>
                            Jurnal akan mendebit akun beban <strong><?= e($bebanKode) ?></strong> dan mengkredit akun persediaan.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url('inventory/item/' . (int) $item['id'])) ?>">Batal</a>
        <button class="btn btn--primary" type="submit" <?= $stok <= 0 ? 'disabled' : '' ?>><?= icon('save', 15) ?> Catat Pengeluaran</button>
    </div>
</form>
