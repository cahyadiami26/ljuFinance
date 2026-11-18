<?php
/**
 * Form purchase order.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $items
 * @var array $partners
 * @var array $inventoryItems
 * @var array $projects
 * @var array $businessUnits
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 * @var array $coaBeban
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
$sel = static fn (string $key, mixed $value): string => (int) ($row[$key] ?? 0) === (int) $value ? 'selected' : '';
?>
<?php if (isset($errors['items'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div><div class="small"><?= e((string) $errors['items']) ?></div></div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('procurement')) : e(url('procurement/' . $id)) ?>" id="po-form">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi PO</h2>
                <div class="card-sub">Nomor PO dibuat otomatis dengan format PO/YYYY/NNNN.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill mono"><?= e((string) $row['nomor']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('partner_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="po-vendor">Vendor</label>
                    <select class="select" id="po-vendor" name="partner_id" required data-po-vendor>
                        <option value="">— pilih vendor —</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>" data-termin="<?= (int) $partner['termin_hari'] ?>" <?= $sel('partner_id', $partner['id']) ?>>
                                <?= e($partner['kode'] . ' — ' . $partner['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('partner_id') ?>
                </div>

                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="po-tanggal">Tanggal PO</label>
                    <input class="input" type="date" id="po-tanggal" name="tanggal" value="<?= e((string) ($row['tanggal'] ?? today())) ?>" required>
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="po-termin">Termin Pembayaran (hari)</label>
                    <input class="input" type="number" min="0" max="365" id="po-termin" name="termin_hari" value="<?= (int) ($row['termin_hari'] ?? 30) ?>">
                </div>

                <div class="field">
                    <label class="field-label" for="po-project">Proyek</label>
                    <select class="select" id="po-project" name="project_id">
                        <option value="">— bukan untuk proyek —</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= $sel('project_id', $project['id']) ?>><?= e($project['kode'] . ' — ' . $project['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="po-unit">Unit Usaha</label>
                    <select class="select" id="po-unit" name="business_unit_id">
                        <option value="">— tanpa unit —</option>
                        <?php foreach ($businessUnits as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" <?= $sel('business_unit_id', $unit['id']) ?>><?= e((string) $unit['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="po-bidang">Bidang</label>
                    <select class="select" id="po-bidang" name="bidang_id">
                        <option value="">— tanpa bidang —</option>
                        <?php foreach ($bidang as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $sel('bidang_id', $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="po-divisi">Divisi</label>
                    <select class="select" id="po-divisi" name="divisi_id">
                        <option value="">— tanpa divisi —</option>
                        <?php foreach ($divisi as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $sel('divisi_id', $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="po-cc">Cost Center</label>
                    <select class="select" id="po-cc" name="cost_center_id">
                        <option value="">— tanpa cost center —</option>
                        <?php foreach ($costCenters as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $sel('cost_center_id', $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="po-catatan">Catatan</label>
                    <input class="input" type="text" id="po-catatan" name="catatan" maxlength="255"
                           value="<?= e((string) ($row['catatan'] ?? '')) ?>" placeholder="Syarat pengiriman, nomor permintaan, dsb.">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rincian Barang / Jasa</h2>
                <div class="card-sub">Baris dengan barang persediaan dapat diterima menjadi stok.</div>
            </div>
            <button class="btn btn--sm" type="button" id="add-item"><?= icon('plus', 14) ?> Tambah Baris</button>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="table" id="po-items">
                    <thead>
                        <tr>
                            <th style="width:190px">Barang Persediaan</th>
                            <th>Deskripsi</th>
                            <th style="width:150px">Akun</th>
                            <th class="num" style="width:90px">Qty</th>
                            <th style="width:80px">Satuan</th>
                            <th class="num" style="width:130px">Harga</th>
                            <th class="num" style="width:80px">PPN %</th>
                            <th class="num" style="width:130px">Jumlah</th>
                            <th style="width:45px"></th>
                        </tr>
                    </thead>
                    <tbody id="po-body">
                        <?php foreach (array_values($items) as $index => $item): ?>
                            <tr>
                                <td>
                                    <select class="select" name="items[<?= $index ?>][item_id]" data-po-item>
                                        <option value="">— jasa / non-stok —</option>
                                        <?php foreach ($inventoryItems as $barang): ?>
                                            <option value="<?= (int) $barang['id'] ?>"
                                                    data-nama="<?= e((string) $barang['nama']) ?>"
                                                    data-satuan="<?= e((string) $barang['satuan']) ?>"
                                                    data-coa="<?= (int) ($barang['coa_persediaan_id'] ?? 0) ?>"
                                                    <?= (int) ($item['item_id'] ?? 0) === (int) $barang['id'] ? 'selected' : '' ?>>
                                                <?= e($barang['kode'] . ' — ' . $barang['nama']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="input" type="text" name="items[<?= $index ?>][deskripsi]" maxlength="255" value="<?= e((string) ($item['deskripsi'] ?? '')) ?>" data-po-deskripsi></td>
                                <td>
                                    <select class="select" name="items[<?= $index ?>][coa_id]">
                                        <option value="">— pilih akun —</option>
                                        <?php foreach ($coaBeban as $coa): ?>
                                            <option value="<?= (int) $coa['id'] ?>" <?= (int) ($item['coa_id'] ?? 0) === (int) $coa['id'] ? 'selected' : '' ?>><?= e($coa['kode'] . ' ' . $coa['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="input mono num" type="number" step="0.0001" min="0" name="items[<?= $index ?>][qty]" value="<?= e((string) ($item['qty'] ?? 1)) ?>" data-po-qty></td>
                                <td><input class="input" type="text" name="items[<?= $index ?>][satuan]" maxlength="20" value="<?= e((string) ($item['satuan'] ?? 'unit')) ?>" data-po-satuan></td>
                                <td><input class="input mono num" type="text" inputmode="decimal" name="items[<?= $index ?>][harga]" value="<?= e(number_format((float) ($item['harga'] ?? 0), 2, ',', '.')) ?>" data-po-harga></td>
                                <td><input class="input mono num" type="number" step="0.01" min="0" max="100" name="items[<?= $index ?>][ppn_persen]" value="<?= e(number_format((float) ($item['ppn_persen'] ?? 11), 2, '.', '')) ?>" data-po-ppn></td>
                                <td class="num mono" data-po-total>0,00</td>
                                <td><button class="btn btn--sm btn--ghost" type="button" data-po-hapus>×</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row" style="justify-content:flex-end;gap:24px;margin-top:16px">
                <div>
                    <div class="tiny muted">Subtotal</div>
                    <div class="mono strong" id="po-subtotal">Rp 0</div>
                </div>
                <div>
                    <div class="tiny muted">PPN</div>
                    <div class="mono strong" id="po-ppn">Rp 0</div>
                </div>
                <div>
                    <div class="tiny muted">Total</div>
                    <div class="mono strong" id="po-total" style="font-size:16px">Rp 0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url($id === null ? 'procurement' : 'procurement/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan PO</button>
    </div>
</form>

<script>
(function () {
    var body = document.getElementById('po-body');
    if (!body) { return; }

    function angka(value) {
        var bersih = String(value || '').replace(/[^0-9,.-]/g, '').replace(/\./g, '').replace(',', '.');
        return parseFloat(bersih) || 0;
    }

    function rupiah(nilai) {
        return 'Rp ' + (Number(nilai) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function hitung() {
        var subtotal = 0;
        var ppn = 0;
        body.querySelectorAll('tr').forEach(function (tr) {
            var qty = angka(tr.querySelector('[data-po-qty]') ? tr.querySelector('[data-po-qty]').value : 0);
            var harga = angka(tr.querySelector('[data-po-harga]') ? tr.querySelector('[data-po-harga]').value : 0);
            var persen = angka(tr.querySelector('[data-po-ppn]') ? tr.querySelector('[data-po-ppn]').value : 0);
            var jumlah = qty * harga;
            var pajak = jumlah * persen / 100;
            subtotal += jumlah;
            ppn += pajak;
            var sel = tr.querySelector('[data-po-total]');
            if (sel) { sel.textContent = rupiah(jumlah); }
        });
        document.getElementById('po-subtotal').textContent = rupiah(subtotal);
        document.getElementById('po-ppn').textContent = rupiah(ppn);
        document.getElementById('po-total').textContent = rupiah(subtotal + ppn);
    }

    function tambah() {
        var index = body.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><select class="select" name="items[' + index + '][item_id]" data-po-item><option value="">— jasa / non-stok —</option></select></td>'
            + '<td><input class="input" type="text" name="items[' + index + '][deskripsi]" maxlength="255" data-po-deskripsi></td>'
            + '<td><select class="select" name="items[' + index + '][coa_id]"><option value="">— pilih akun —</option></select></td>'
            + '<td><input class="input mono num" type="number" step="0.0001" min="0" name="items[' + index + '][qty]" value="1" data-po-qty></td>'
            + '<td><input class="input" type="text" name="items[' + index + '][satuan]" value="unit" data-po-satuan></td>'
            + '<td><input class="input mono num" type="text" inputmode="decimal" name="items[' + index + '][harga]" value="0,00" data-po-harga></td>'
            + '<td><input class="input mono num" type="number" step="0.01" min="0" max="100" name="items[' + index + '][ppn_persen]" value="11" data-po-ppn></td>'
            + '<td class="num mono" data-po-total>0,00</td>'
            + '<td><button class="btn btn--sm btn--ghost" type="button" data-po-hapus>×</button></td>';

        var first = body.querySelector('tr');
        if (first) {
            var opts = first.querySelector('[data-po-item]');
            if (opts) { tr.querySelector('[data-po-item]').innerHTML = opts.innerHTML; }
            var coa = first.querySelector('select[name$="[coa_id]"]');
            if (coa) { tr.querySelector('select[name$="[coa_id]"]').innerHTML = coa.innerHTML; }
        }

        body.appendChild(tr);
        hitung();
    }

    body.addEventListener('input', hitung);
    body.addEventListener('change', function (event) {
        var select = event.target.closest('[data-po-item]');
        if (select) {
            var opsi = select.options[select.selectedIndex];
            var tr = select.closest('tr');
            if (opsi && opsi.value && tr) {
                var deskripsi = tr.querySelector('[data-po-deskripsi]');
                var satuan = tr.querySelector('[data-po-satuan]');
                var coa = tr.querySelector('select[name$="[coa_id]"]');
                if (deskripsi && !deskripsi.value) { deskripsi.value = opsi.getAttribute('data-nama') || ''; }
                if (satuan) { satuan.value = opsi.getAttribute('data-satuan') || 'unit'; }
                if (coa) { coa.value = opsi.getAttribute('data-coa') || ''; }
            }
        }
        hitung();
    });

    body.addEventListener('click', function (event) {
        var tombol = event.target.closest('[data-po-hapus]');
        if (!tombol) { return; }
        var tr = tombol.closest('tr');
        if (tr && body.querySelectorAll('tr').length > 1) { tr.remove(); hitung(); }
    });

    var add = document.getElementById('add-item');
    if (add) { add.addEventListener('click', tambah); }

    var vendor = document.querySelector('[data-po-vendor]');
    if (vendor) {
        vendor.addEventListener('change', function () {
            var opsi = vendor.options[vendor.selectedIndex];
            var termin = document.getElementById('po-termin');
            if (opsi && opsi.getAttribute('data-termin') && termin) { termin.value = opsi.getAttribute('data-termin'); }
        });
    }

    hitung();
})();
</script>
