<?php
/**
 * Form penerimaan barang manual.
 *
 * @var array $item
 * @var array $warehouses
 * @var array $defaults
 */
?>
<form method="post" action="<?= e(url('inventory/item/' . (int) $item['id'] . '/masuk')) ?>">
    <?= csrf_field() ?>
    <div class="grid-main mb-16">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Penerimaan Barang</h2>
                    <div class="card-sub">Harga rata-rata dihitung ulang setelah penerimaan.</div>
                </div>
                <span class="pill mono"><?= e((string) $item['kode']) ?></span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="m-tanggal">Tanggal</label>
                        <input class="input" type="date" id="m-tanggal" name="tanggal" value="<?= e((string) $defaults['tanggal']) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="m-gudang">Gudang</label>
                        <select class="select" id="m-gudang" name="warehouse_id">
                            <option value="">— tanpa gudang —</option>
                            <?php foreach ($warehouses as $gudang): ?>
                                <option value="<?= (int) $gudang['id'] ?>" <?= (int) ($defaults['warehouse_id'] ?? 0) === (int) $gudang['id'] ? 'selected' : '' ?>><?= e((string) $gudang['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="m-qty">Qty Masuk (<?= e((string) $item['satuan']) ?>)</label>
                        <input class="input" type="number" step="0.0001" min="0.0001" id="m-qty" name="qty"
                               value="<?= e((string) $defaults['qty']) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="m-harga">Harga Satuan (Rp)</label>
                        <input class="input mono" type="text" inputmode="decimal" id="m-harga" name="harga"
                               value="<?= e(number_format((float) $defaults['harga'], 2, ',', '.')) ?>" required>
                    </div>
                    <div class="field field--full">
                        <label class="field-label" for="m-ket">Keterangan</label>
                        <input class="input" type="text" id="m-ket" name="keterangan" maxlength="255"
                               value="<?= e((string) $defaults['keterangan']) ?>" placeholder="Penerimaan dari vendor / koreksi stok">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Posisi Stok</h2>
                    <div class="card-sub">Sebelum transaksi</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="small muted">Stok saat ini</span>
                    <span class="mono strong"><?= e(number_format((float) $item['stok'], 2, ',', '.')) ?> <?= e((string) $item['satuan']) ?></span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="small muted">Harga rata-rata</span>
                    <span class="mono strong"><?= e(rupiah((float) $item['harga_rata'])) ?></span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0">
                    <span class="small muted">Nilai persediaan</span>
                    <span class="mono strong"><?= e(rupiah((float) $item['stok'] * (float) $item['harga_rata'])) ?></span>
                </div>
                <div class="notice notice--info mt-12">
                    <?= icon('info', 15) ?>
                    <div class="tiny">Penerimaan manual tidak membentuk jurnal. Jurnal pembelian dibuat saat tagihan AP diposting.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url('inventory/item/' . (int) $item['id'])) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Penerimaan</button>
    </div>
</form>
