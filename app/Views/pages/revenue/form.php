<?php
/**
 * Form pengakuan pendapatan kontrak.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $projects
 * @var array $contracts
 * @var array $metode
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
$selected = static function (string $field, int|string|null $value) use ($row): string {
    return (string) ($row[$field] ?? '') === (string) $value ? 'selected' : '';
};
?>
<?php if (isset($errors['general'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Pengakuan belum dapat disimpan</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('revenue')) : e(url('revenue/' . $id)) ?>" id="revenue-form" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Pengakuan</h2>
                <div class="card-sub">Nomor dokumen REV dibuat otomatis. Jurnal: Debit Piutang Belum Ditagih, Kredit Pendapatan Usaha.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill"><span class="mono"><?= e((string) $row['nomor']) ?></span></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tanggal">Tanggal Pengakuan</label>
                    <input class="input" type="date" id="f-tanggal" name="tanggal" required value="<?= e((string) $row['tanggal']) ?>">
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field<?= $hasError('metode') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-metode">Metode Pengakuan</label>
                    <select class="select" id="f-metode" name="metode">
                        <?php foreach ($metode as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) $row['metode'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('metode') ?>
                </div>

                <div class="field field--full<?= $hasError('contract_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-contract">Kontrak</label>
                    <select class="select" id="f-contract" name="contract_id" data-contract>
                        <option value="">— tanpa kontrak —</option>
                        <?php foreach ($contracts as $contract): ?>
                            <option value="<?= (int) $contract['id'] ?>"
                                    data-project="<?= (int) ($contract['project_id'] ?? 0) ?>"
                                    <?= $selected('contract_id', (int) $contract['id']) ?>>
                                <?= e($contract['nomor'] . ' — ' . $contract['judul'] . ' · ' . rupiah_short((float) $contract['nilai_kontrak']) . ' · ' . (string) $contract['status']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('contract_id') ?>
                </div>

                <div class="field<?= $hasError('project_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-project">Proyek</label>
                    <select class="select" id="f-project" name="project_id">
                        <option value="">— tanpa proyek —</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= $selected('project_id', (int) $project['id']) ?>><?= e((string) $project['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('project_id') ?>
                </div>

                <div class="field<?= $hasError('persentase') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-persentase">Persentase Penyelesaian</label>
                    <input class="input" type="number" min="0" max="100" step="0.01" id="f-persentase" name="persentase"
                           value="<?= e((string) $row['persentase']) ?>" placeholder="0">
                    <div class="tiny muted">Dipakai untuk metode persentase penyelesaian (kumulatif).</div>
                    <?= $errorText('persentase') ?>
                </div>

                <div class="field<?= $hasError('nilai') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-nilai">Nilai Pengakuan</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="f-nilai" name="nilai"
                           value="<?= (float) $row['nilai'] > 0 ? e(number_format((float) $row['nilai'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <div class="tiny muted">Kosongkan untuk dihitung otomatis dari metode &amp; nilai kontrak.</div>
                    <?= $errorText('nilai') ?>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="f-keterangan">Keterangan</label>
                    <input class="input" type="text" id="f-keterangan" name="keterangan" maxlength="255"
                           value="<?= e((string) ($row['keterangan'] ?? '')) ?>" placeholder="Dasar pengakuan, berita acara, atau nomor progres">
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= $id === null ? e(url('revenue')) : e(url('revenue/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Pengakuan</button>
    </div>
</form>
