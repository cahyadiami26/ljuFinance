<?php
/**
 * Form dokumen pajak.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $types
 * @var array $tipeList
 * @var array $jenisList
 * @var array $partners
 * @var array $periodeList
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
?>
<?php if (isset($errors['general'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Dokumen belum dapat disimpan</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('tax')) : e(url('tax/' . $id)) ?>" id="tax-form">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Identitas Dokumen</h2>
                <div class="card-sub">Nomor faktur/bukti potong dari DJP diisi manual; SSP dapat dikosongkan untuk penomoran otomatis.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('nomor') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-nomor">Nomor Dokumen</label>
                    <input class="input" type="text" id="t-nomor" name="nomor" maxlength="80"
                           value="<?= e((string) ($row['nomor'] ?? '')) ?>" placeholder="otomatis untuk SSP">
                    <?= $errorText('nomor') ?>
                </div>

                <div class="field<?= $hasError('tipe') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-tipe">Tipe Dokumen</label>
                    <select class="select" id="t-tipe" name="tipe" required>
                        <?php foreach ($tipeList as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($row['tipe'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('tipe') ?>
                </div>

                <div class="field<?= $hasError('tax_type_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-jenis">Jenis Pajak</label>
                    <select class="select" id="t-jenis" name="tax_type_id" required data-tax-type>
                        <option value="">— pilih jenis —</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= (int) $type['id'] ?>"
                                    data-tarif="<?= e((string) $type['tarif']) ?>"
                                    <?= (int) ($row['tax_type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>>
                                <?= e($type['kode'] . ' — ' . $type['nama'] . ' (' . number_format((float) $type['tarif'], 2, ',', '.') . '%)') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('tax_type_id') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="t-partner">Partner / Vendor</label>
                    <select class="select" id="t-partner" name="partner_id">
                        <option value="">—</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>" <?= (int) ($row['partner_id'] ?? 0) === (int) $partner['id'] ? 'selected' : '' ?>>
                                <?= e((string) $partner['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-tanggal">Tanggal</label>
                    <input class="input" type="date" id="t-tanggal" name="tanggal" required value="<?= e((string) ($row['tanggal'] ?? today())) ?>">
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="t-npwp">NPWP</label>
                    <input class="input" type="text" id="t-npwp" name="npwp" maxlength="30"
                           value="<?= e((string) ($row['npwp'] ?? '')) ?>" placeholder="00.000.000.0-000.000">
                </div>

                <div class="field<?= $hasError('dpp') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-dpp">DPP</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="t-dpp" name="dpp" data-tax-dpp
                           value="<?= (float) ($row['dpp'] ?? 0) > 0 ? e(number_format((float) $row['dpp'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <?= $errorText('dpp') ?>
                </div>

                <div class="field<?= $hasError('tarif') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-tarif">Tarif (%)</label>
                    <input class="input" type="number" step="0.01" min="0" max="100" id="t-tarif" name="tarif" data-tax-tarif
                           value="<?= e(number_format((float) ($row['tarif'] ?? 0), 2, '.', '')) ?>">
                    <?= $errorText('tarif') ?>
                </div>

                <div class="field<?= $hasError('nilai_pajak') ? ' has-error' : '' ?>">
                    <label class="field-label" for="t-pajak">Nilai Pajak</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="t-pajak" name="nilai_pajak" data-tax-nilai
                           value="<?= (float) ($row['nilai_pajak'] ?? 0) > 0 ? e(number_format((float) $row['nilai_pajak'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <div class="tiny muted">Dihitung otomatis dari DPP × tarif bila dikosongkan.</div>
                    <?= $errorText('nilai_pajak') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="t-refmodule">Modul Referensi</label>
                    <input class="input" type="text" id="t-refmodule" name="ref_module" maxlength="40"
                           value="<?= e((string) ($row['ref_module'] ?? '')) ?>" placeholder="ap / ar / expense / journal">
                </div>

                <div class="field">
                    <label class="field-label" for="t-refid">ID Referensi</label>
                    <input class="input" type="number" min="0" id="t-refid" name="ref_id"
                           value="<?= (int) ($row['ref_id'] ?? 0) ?>">
                </div>

                <div class="field field--full">
                    <label class="field-label" for="t-keterangan">Keterangan</label>
                    <input class="input" type="text" id="t-keterangan" name="keterangan" maxlength="255"
                           value="<?= e((string) ($row['keterangan'] ?? '')) ?>" placeholder="Opsional">
                </div>
            </div>

            <div class="notice mt-16">
                <?= icon('info', 16) ?>
                <div>
                    Jurnal PPN/PPh dari transaksi AP, AR, dan biaya sudah dibuat di modul asal. Dokumen pajak di sini
                    menjadi bukti pelaporan; jurnal dibuat hanya saat <strong>SSP disetorkan</strong>.
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= $id === null ? e(url('tax')) : e(url('tax/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Dokumen</button>
    </div>
</form>
