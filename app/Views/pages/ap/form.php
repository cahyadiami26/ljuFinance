<?php
/**
 * Form tagihan vendor (AP).
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $partners
 * @var array $coa
 * @var array $projects
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 * @var float $ppnTarif
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
$selected = static function (string $field, int|string|null $value) use ($row): string {
    return (string) ($row[$field] ?? '') === (string) $value ? 'selected' : '';
};

$coaOptions = static function (int $selectedId) use ($coa): string {
    $html = '<option value="">— pilih akun —</option>';
    foreach ($coa as $akun) {
        $html .= '<option value="' . (int) $akun['id'] . '"' . ($selectedId === (int) $akun['id'] ? ' selected' : '') . '>'
            . e($akun['kode'] . ' — ' . $akun['nama']) . '</option>';
    }
    return $html;
};

$items = $row['items'] ?? [];
if ($items === []) {
    $items = [['keterangan' => '', 'coa_id' => 0, 'jumlah' => 0, 'ppn' => 0]];
}
?>
<?php if (isset($errors['general']) || isset($errors['items'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Tagihan belum dapat disimpan</div>
            <?php if (isset($errors['general'])): ?><div class="small"><?= e((string) $errors['general']) ?></div><?php endif; ?>
            <?php if (isset($errors['items'])): ?><div class="small"><?= e((string) $errors['items']) ?></div><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('ap')) : e(url('ap/' . $id)) ?>" id="invoice-form" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Tagihan</h2>
                <div class="card-sub">Nomor dokumen AP dibuat otomatis berdasarkan tanggal tagihan.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill"><span class="mono"><?= e((string) $row['nomor']) ?></span></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field field--full<?= $hasError('partner_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-partner">Vendor</label>
                    <select class="select" id="f-partner" name="partner_id" data-partner required>
                        <option value="">— pilih vendor —</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>"
                                    data-termin="<?= (int) ($partner['termin_hari'] ?? 30) ?>"
                                    <?= $selected('partner_id', (int) $partner['id']) ?>>
                                <?= e($partner['kode'] . ' — ' . $partner['nama'] . (!empty($partner['npwp']) ? ' · NPWP ' . $partner['npwp'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('partner_id') ?>
                </div>

                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tanggal">Tanggal Tagihan</label>
                    <input class="input" type="date" id="f-tanggal" name="tanggal" data-tanggal-tagihan required value="<?= e((string) $row['tanggal']) ?>">
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field<?= $hasError('termin_hari') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-termin">Termin (hari)</label>
                    <input class="input" type="number" min="0" max="365" step="1" id="f-termin" name="termin_hari" data-termin value="<?= (int) ($row['termin_hari'] ?? 30) ?>">
                    <?= $errorText('termin_hari') ?>
                </div>

                <div class="field<?= $hasError('tanggal_jatuh_tempo') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-jatuh-tempo">Jatuh Tempo</label>
                    <input class="input" type="date" id="f-jatuh-tempo" name="tanggal_jatuh_tempo" data-jatuh-tempo required value="<?= e((string) $row['tanggal_jatuh_tempo']) ?>">
                    <?= $errorText('tanggal_jatuh_tempo') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="f-supplier">No. Tagihan Supplier</label>
                    <input class="input" type="text" id="f-supplier" name="nomor_supplier" maxlength="80" value="<?= e((string) ($row['nomor_supplier'] ?? '')) ?>" placeholder="mis. INV/2026/0112">
                </div>

                <div class="field">
                    <label class="field-label" for="f-termin-ke">Termin Ke</label>
                    <input class="input" type="number" min="0" id="f-termin-ke" name="termin_ke" value="<?= (int) ($row['termin_ke'] ?? 0) ?: '' ?>" placeholder="opsional">
                </div>

                <div class="field">
                    <label class="field-label" for="f-project">Proyek</label>
                    <select class="select" id="f-project" name="project_id">
                        <option value="">— tanpa proyek —</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= $selected('project_id', (int) $project['id']) ?>><?= e((string) $project['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="f-bidang">Bidang</label>
                    <select class="select" id="f-bidang" name="bidang_id">
                        <option value="">—</option>
                        <?php foreach ($bidang as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $selected('bidang_id', (int) $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="f-divisi">Divisi</label>
                    <select class="select" id="f-divisi" name="divisi_id">
                        <option value="">—</option>
                        <?php foreach ($divisi as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $selected('divisi_id', (int) $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="f-cost-center">Cost Center</label>
                    <select class="select" id="f-cost-center" name="cost_center_id">
                        <option value="">—</option>
                        <?php foreach ($costCenters as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $selected('cost_center_id', (int) $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="f-keterangan">Keterangan</label>
                    <input class="input" type="text" id="f-keterangan" name="keterangan" maxlength="255"
                           value="<?= e((string) ($row['keterangan'] ?? '')) ?>" placeholder="Uraian singkat tagihan (opsional)">
                </div>
            </div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rincian Tagihan</h2>
                <div class="card-sub">Gunakan akun beban/aset yang boleh menerima posting. PPN masukan dihitung per baris.</div>
            </div>
            <button class="btn btn--sm" type="button" data-rowset-add="invoice-items"><?= icon('plus', 14) ?> Tambah Baris</button>
        </div>
        <div class="table-wrap">
            <table class="table invoice-lines">
                <thead>
                    <tr>
                        <th style="min-width:240px">Keterangan</th>
                        <th style="min-width:220px">Akun COA</th>
                        <th class="num" style="width:140px">Jumlah</th>
                        <th class="num" style="width:140px">PPN Masukan</th>
                        <th style="width:46px"></th>
                    </tr>
                </thead>
                <tbody data-rowset="invoice-items">
                    <?php foreach ($items as $index => $item): ?>
                        <tr data-row>
                            <td><input class="input input--sm" type="text" name="items[<?= $index ?>][keterangan]" maxlength="255" value="<?= e((string) ($item['keterangan'] ?? '')) ?>" placeholder="Uraian tagihan" required></td>
                            <td><select class="select select--sm" name="items[<?= $index ?>][coa_id]"><?= $coaOptions((int) ($item['coa_id'] ?? 0)) ?></select></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-jumlah name="items[<?= $index ?>][jumlah]" value="<?= (float) ($item['jumlah'] ?? 0) > 0 ? e(number_format((float) $item['jumlah'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-ppn name="items[<?= $index ?>][ppn]" value="<?= (float) ($item['ppn'] ?? 0) > 0 ? e(number_format((float) $item['ppn'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus baris"><?= icon('x', 14) ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="right strong small">SUBTOTAL</td>
                        <td class="num strong" colspan="2" data-invoice-total="subtotal">Rp 0</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="right small">PPN MASUKAN</td>
                        <td class="num" colspan="2" data-invoice-total="ppn">Rp 0</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="right strong small">TOTAL TAGIHAN</td>
                        <td class="num strong" colspan="2" data-invoice-total="total">Rp 0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border)">
            <div class="row" style="justify-content:space-between">
                <span class="tiny muted">Total tagihan = subtotal + PPN masukan (tarif PPN <?= e((string) $ppnTarif) ?>%).</span>
                <span class="term-badge"><?= icon('clock', 12) ?> Jatuh tempo mengikuti termin vendor</span>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= $id === null ? e(url('ap')) : e(url('ap/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Tagihan</button>
    </div>
</form>

<template id="invoice-items-template">
    <tr data-row>
        <td><input class="input input--sm" type="text" name="items[__INDEX__][keterangan]" maxlength="255" placeholder="Uraian tagihan" required></td>
        <td><select class="select select--sm" name="items[__INDEX__][coa_id]"><?= $coaOptions(0) ?></select></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-jumlah name="items[__INDEX__][jumlah]" placeholder="0"></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-ppn name="items[__INDEX__][ppn]" placeholder="0"></td>
        <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus baris"><?= icon('x', 14) ?></button></td>
    </tr>
</template>
