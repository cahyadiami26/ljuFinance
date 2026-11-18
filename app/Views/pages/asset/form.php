<?php
/**
 * Form aset tetap.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $categories
 * @var array $users
 * @var array $bidang
 * @var array $divisi
 * @var array $projects
 * @var array $banks
 * @var array $metode
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
?>
<?php if (isset($errors['general'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Aset belum dapat disimpan</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('asset')) : e(url('asset/' . $id)) ?>" id="asset-form">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Identitas Aset</h2>
                <div class="card-sub">Kode inventaris otomatis bila dikosongkan (format AST/YYYY/NNNN).</div>
            </div>
            <?php if (!empty($row['kode'])): ?>
                <span class="pill mono"><?= e((string) $row['kode']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('kode') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-kode">Kode Inventaris</label>
                    <input class="input" type="text" id="a-kode" name="kode" maxlength="40"
                           value="<?= e((string) ($row['kode'] ?? '')) ?>" placeholder="otomatis">
                    <?= $errorText('kode') ?>
                </div>

                <div class="field<?= $hasError('category_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-kategori">Kategori</label>
                    <select class="select" id="a-kategori" name="category_id" required data-asset-kategori>
                        <option value="">— pilih kategori —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>"
                                    data-umur="<?= (int) $cat['umur_bulan'] ?>"
                                    data-metode="<?= e($metode[(string) $cat['metode']] ?? $cat['metode']) ?>"
                                    <?= (int) ($row['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['kode'] . ' — ' . $cat['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="tiny muted" data-asset-metode>Metode mengikuti kategori.</div>
                    <?= $errorText('category_id') ?>
                </div>

                <div class="field field--full<?= $hasError('nama') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-nama">Nama Aset</label>
                    <input class="input" type="text" id="a-nama" name="nama" maxlength="150" required
                           value="<?= e((string) ($row['nama'] ?? '')) ?>" placeholder="mis. Excavator Komatsu PC200">
                    <?= $errorText('nama') ?>
                </div>

                <div class="field<?= $hasError('tanggal_perolehan') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-tanggal">Tanggal Perolehan</label>
                    <input class="input" type="date" id="a-tanggal" name="tanggal_perolehan" required
                           value="<?= e((string) ($row['tanggal_perolehan'] ?? today())) ?>">
                    <?= $errorText('tanggal_perolehan') ?>
                </div>

                <div class="field<?= $hasError('no_invoice') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-invoice">No. Invoice / Dokumen</label>
                    <input class="input" type="text" id="a-invoice" name="no_invoice" maxlength="80"
                           value="<?= e((string) ($row['no_invoice'] ?? '')) ?>" placeholder="Opsional">
                </div>

                <div class="field<?= $hasError('harga_perolehan') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-harga">Harga Perolehan</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="a-harga" name="harga_perolehan" required
                           value="<?= (float) ($row['harga_perolehan'] ?? 0) > 0 ? e(number_format((float) $row['harga_perolehan'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <?= $errorText('harga_perolehan') ?>
                </div>

                <div class="field<?= $hasError('nilai_residu') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-residu">Nilai Residu</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="a-residu" name="nilai_residu"
                           value="<?= (float) ($row['nilai_residu'] ?? 0) > 0 ? e(number_format((float) $row['nilai_residu'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <?= $errorText('nilai_residu') ?>
                </div>

                <div class="field<?= $hasError('umur_bulan') ? ' has-error' : '' ?>">
                    <label class="field-label" for="a-umur">Umur Ekonomis (bulan)</label>
                    <input class="input" type="number" min="0" max="600" id="a-umur" name="umur_bulan" data-asset-umur
                           value="<?= (int) ($row['umur_bulan'] ?? 0) ?>">
                    <div class="tiny muted">Kosongkan untuk memakai umur default kategori.</div>
                    <?= $errorText('umur_bulan') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="a-lokasi">Lokasi</label>
                    <input class="input" type="text" id="a-lokasi" name="lokasi" maxlength="150"
                           value="<?= e((string) ($row['lokasi'] ?? '')) ?>" placeholder="mis. Site Way Sekampung">
                </div>

                <div class="field">
                    <label class="field-label" for="a-pj">Penanggung Jawab</label>
                    <select class="select" id="a-pj" name="penanggung_jawab_user_id">
                        <option value="">—</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" <?= (int) ($row['penanggung_jawab_user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                                <?= e((string) $user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="a-bidang">Bidang</label>
                    <select class="select" id="a-bidang" name="bidang_id">
                        <option value="">—</option>
                        <?php foreach ($bidang as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= (int) ($row['bidang_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="a-divisi">Divisi</label>
                    <select class="select" id="a-divisi" name="divisi_id">
                        <option value="">—</option>
                        <?php foreach ($divisi as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= (int) ($row['divisi_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="a-project">Proyek</label>
                    <select class="select" id="a-project" name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= (int) ($row['project_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="a-keterangan">Keterangan</label>
                    <input class="input" type="text" id="a-keterangan" name="keterangan" maxlength="255"
                           value="<?= e((string) ($row['keterangan'] ?? '')) ?>" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= $id === null ? e(url('asset')) : e(url('asset/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Aset</button>
    </div>
</form>
