<?php
/**
 * Form master barang persediaan.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $kategoriList
 * @var array $coaPersediaan
 * @var array $coaBeban
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
?>
<form method="post" action="<?= $id === null ? e(url('inventory/item')) : e(url('inventory/item/' . $id)) ?>">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Identitas Barang</h2>
                <div class="card-sub">Stok awal nol; stok bertambah melalui penerimaan barang atau PO.</div>
            </div>
            <?php if (!empty($row['kode'])): ?>
                <span class="pill mono"><?= e((string) $row['kode']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('kode') ? ' has-error' : '' ?>">
                    <label class="field-label" for="i-kode">Kode Barang</label>
                    <input class="input mono" type="text" id="i-kode" name="kode" maxlength="30" required
                           value="<?= e((string) ($row['kode'] ?? '')) ?>" placeholder="MAT-001">
                    <?= $errorText('kode') ?>
                </div>

                <div class="field<?= $hasError('nama') ? ' has-error' : '' ?>">
                    <label class="field-label" for="i-nama">Nama Barang</label>
                    <input class="input" type="text" id="i-nama" name="nama" maxlength="150" required
                           value="<?= e((string) ($row['nama'] ?? '')) ?>" placeholder="Nama material / barang">
                    <?= $errorText('nama') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="i-kategori">Kategori</label>
                    <select class="select" id="i-kategori" name="kategori">
                        <?php foreach ($kategoriList as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($row['kategori'] ?? 'material') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="i-satuan">Satuan</label>
                    <input class="input" type="text" id="i-satuan" name="satuan" maxlength="20"
                           value="<?= e((string) ($row['satuan'] ?? 'unit')) ?>" placeholder="unit / kg / m3">
                </div>

                <div class="field<?= $hasError('coa_persediaan_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="i-coa-persediaan">Akun Persediaan</label>
                    <select class="select" id="i-coa-persediaan" name="coa_persediaan_id" required>
                        <option value="">— pilih akun —</option>
                        <?php foreach ($coaPersediaan as $coa): ?>
                            <option value="<?= (int) $coa['id'] ?>" <?= (int) ($row['coa_persediaan_id'] ?? 0) === (int) $coa['id'] ? 'selected' : '' ?>>
                                <?= e($coa['kode'] . ' — ' . $coa['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="tiny muted">Akun aset lancar persediaan yang dikredit saat barang keluar.</div>
                    <?= $errorText('coa_persediaan_id') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="i-coa-beban">Akun Beban Pengeluaran</label>
                    <select class="select" id="i-coa-beban" name="coa_beban_id">
                        <option value="">— belum ditentukan —</option>
                        <?php foreach ($coaBeban as $coa): ?>
                            <option value="<?= (int) $coa['id'] ?>" <?= (int) ($row['coa_beban_id'] ?? 0) === (int) $coa['id'] ? 'selected' : '' ?>>
                                <?= e($coa['kode'] . ' — ' . $coa['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="tiny muted">Didebit saat pengeluaran barang; wajib untuk transaksi keluar.</div>
                </div>

                <div class="field">
                    <label class="field-label" for="i-min">Stok Minimum</label>
                    <input class="input" type="number" step="0.01" min="0" id="i-min" name="stok_min"
                           value="<?= e(number_format((float) ($row['stok_min'] ?? 0), 2, '.', '')) ?>">
                    <div class="tiny muted">Peringatan muncul bila stok berada di bawah angka ini.</div>
                </div>

                <div class="field">
                    <label class="field-label" for="i-aktif">Keaktifan</label>
                    <label class="checkbox">
                        <input type="checkbox" id="i-aktif" name="is_active" value="1" <?= (int) ($row['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        Barang aktif
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url($id === null ? 'inventory' : 'inventory/item/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Barang</button>
    </div>
</form>
