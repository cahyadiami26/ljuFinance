<?php
/**
 * Form penerimaan barang atas PO.
 *
 * @var array $po
 * @var array $items
 * @var array $warehouses
 * @var array $defaults
 */
?>
<form method="post" action="<?= e(url('procurement/' . (int) $po['id'] . '/terima')) ?>" id="gr-form">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Data Penerimaan</h2>
                <div class="card-sub">Hanya baris berstatus barang persediaan yang dapat diterima.</div>
            </div>
            <span class="pill mono"><?= e((string) $po['nomor']) ?></span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="gr-tanggal">Tanggal Terima</label>
                    <input class="input" type="date" id="gr-tanggal" name="tanggal" value="<?= e((string) $defaults['tanggal']) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label" for="gr-gudang">Gudang</label>
                    <select class="select" id="gr-gudang" name="warehouse_id">
                        <option value="">— tanpa gudang —</option>
                        <?php foreach ($warehouses as $gudang): ?>
                            <option value="<?= (int) $gudang['id'] ?>"><?= e($gudang['kode'] . ' — ' . $gudang['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($warehouses === []): ?>
                        <div class="tiny muted">Belum ada gudang aktif. Penerimaan tetap dapat dicatat tanpa gudang.</div>
                    <?php endif; ?>
                </div>
                <div class="field field--full">
                    <label class="field-label" for="gr-catatan">Catatan Penerimaan</label>
                    <input class="input" type="text" id="gr-catatan" name="catatan" maxlength="255" value="<?= e((string) $defaults['catatan']) ?>" placeholder="Nomor surat jalan, kondisi barang, dsb.">
                </div>
            </div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rincian Penerimaan</h2>
                <div class="card-sub">Qty tidak boleh melebihi sisa PO</div>
            </div>
            <button class="btn btn--sm" type="button" id="isi-sisa"><?= icon('check', 14) ?> Isi Semua Sisa</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="num" style="width:90px">Qty PO</th>
                        <th class="num" style="width:110px">Sudah Diterima</th>
                        <th class="num" style="width:90px">Sisa</th>
                        <th class="num" style="width:130px">Qty Terima</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $sisa = max(0.0, (float) $item['qty'] - (float) $item['diterima_qty']);
                        $stok = (int) ($item['item_id'] ?? 0) > 0;
                        ?>
                        <tr>
                            <td class="small">
                                <span class="strong"><?= e((string) $item['deskripsi']) ?></span>
                                <div class="tiny muted">
                                    <?= e((string) ($item['item_kode'] ?? 'non-stok')) ?>
                                    <?php if (!$stok): ?> · tidak menambah stok<?php endif; ?>
                                </div>
                            </td>
                            <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',')) ?> <?= e((string) $item['satuan']) ?></td>
                            <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $item['diterima_qty'], 2, ',', '.'), '0'), ',')) ?></td>
                            <td class="num mono strong"><?= e(rtrim(rtrim(number_format($sisa, 2, ',', '.'), '0'), ',')) ?></td>
                            <td>
                                <input class="input mono num" type="number" step="0.0001" min="0" max="<?= e((string) $sisa) ?>"
                                       name="items[<?= (int) $item['id'] ?>]" value="0" data-sisa="<?= e((string) $sisa) ?>"
                                       <?= (!$stok || $sisa <= 0) ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url('procurement/' . (int) $po['id'])) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Penerimaan</button>
    </div>
</form>
<script>
(function () {
    var isi = document.getElementById('isi-sisa');
    if (isi) {
        isi.addEventListener('click', function () {
            document.querySelectorAll('[data-sisa]').forEach(function (input) {
                if (!input.disabled) { input.value = input.getAttribute('data-sisa'); }
            });
        });
    }
})();
</script>
