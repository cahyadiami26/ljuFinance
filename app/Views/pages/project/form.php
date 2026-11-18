<?php
/**
 * Form proyek.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $partners
 * @var array $businessUnits
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 * @var array $users
 * @var array $statusList
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
$sel = static fn (string $key, mixed $value): string => (int) ($row[$key] ?? 0) === (int) $value ? 'selected' : '';
?>
<form method="post" action="<?= $id === null ? e(url('project')) : e(url('project/' . $id)) ?>">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Identitas Proyek</h2>
                <div class="card-sub">Kode unik proyek dipakai pada jurnal, anggaran, dan laporan.</div>
            </div>
            <?php if (!empty($row['kode'])): ?>
                <span class="pill mono"><?= e((string) $row['kode']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('kode') ? ' has-error' : '' ?>">
                    <label class="field-label" for="p-kode">Kode Proyek</label>
                    <input class="input" type="text" id="p-kode" name="kode" maxlength="30" required
                           value="<?= e((string) ($row['kode'] ?? '')) ?>" placeholder="PRJ-2026-001">
                    <?= $errorText('kode') ?>
                </div>

                <div class="field<?= $hasError('nama') ? ' has-error' : '' ?>">
                    <label class="field-label" for="p-nama">Nama Proyek</label>
                    <input class="input" type="text" id="p-nama" name="nama" maxlength="150" required
                           value="<?= e((string) ($row['nama'] ?? '')) ?>" placeholder="Pekerjaan …">
                    <?= $errorText('nama') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="p-partner">Pemberi Kerja</label>
                    <select class="select" id="p-partner" name="partner_id">
                        <option value="">— tanpa pemberi kerja —</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>" <?= $sel('partner_id', $partner['id']) ?>><?= e((string) $partner['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-unit">Unit Usaha</label>
                    <select class="select" id="p-unit" name="business_unit_id">
                        <option value="">— tanpa unit —</option>
                        <?php foreach ($businessUnits as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" <?= $sel('business_unit_id', $unit['id']) ?>><?= e((string) $unit['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-bidang">Bidang</label>
                    <select class="select" id="p-bidang" name="bidang_id">
                        <option value="">— tanpa bidang —</option>
                        <?php foreach ($bidang as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $sel('bidang_id', $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-divisi">Divisi</label>
                    <select class="select" id="p-divisi" name="divisi_id">
                        <option value="">— tanpa divisi —</option>
                        <?php foreach ($divisi as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $sel('divisi_id', $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-cc">Cost Center</label>
                    <select class="select" id="p-cc" name="cost_center_id">
                        <option value="">— tanpa cost center —</option>
                        <?php foreach ($costCenters as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $sel('cost_center_id', $item['id']) ?>><?= e((string) $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-manager">Project Manager</label>
                    <select class="select" id="p-manager" name="manager_user_id">
                        <option value="">— belum ditentukan —</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" <?= $sel('manager_user_id', $user['id']) ?>><?= e((string) $user['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-nilai">Nilai Kontrak (Rp)</label>
                    <input class="input mono" type="text" id="p-nilai" name="nilai_kontrak" inputmode="decimal"
                           value="<?= e(number_format((float) ($row['nilai_kontrak'] ?? 0), 2, ',', '.')) ?>">
                    <div class="tiny muted">Nilai proyek menurut kontrak induk; nilai rinci per kontrak dikelola di modul kontrak.</div>
                </div>

                <div class="field">
                    <label class="field-label" for="p-um">Uang Muka (%)</label>
                    <input class="input" type="number" step="0.01" min="0" max="100" id="p-um" name="uang_muka_persen"
                           value="<?= e(number_format((float) ($row['uang_muka_persen'] ?? 0), 2, '.', '')) ?>">
                </div>

                <div class="field">
                    <label class="field-label" for="p-mulai">Tanggal Mulai</label>
                    <input class="input" type="date" id="p-mulai" name="tanggal_mulai" value="<?= e((string) ($row['tanggal_mulai'] ?? '')) ?>">
                </div>

                <div class="field<?= $hasError('tanggal_selesai') ? ' has-error' : '' ?>">
                    <label class="field-label" for="p-selesai">Target Selesai</label>
                    <input class="input" type="date" id="p-selesai" name="tanggal_selesai" value="<?= e((string) ($row['tanggal_selesai'] ?? '')) ?>">
                    <?= $errorText('tanggal_selesai') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="p-status">Status</label>
                    <select class="select" id="p-status" name="status">
                        <?php foreach ($statusList as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($row['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="p-aktif">Keaktifan</label>
                    <label class="checkbox">
                        <input type="checkbox" id="p-aktif" name="is_active" value="1" <?= (int) ($row['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        Proyek aktif dan dapat dipilih pada transaksi
                    </label>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="p-ket">Keterangan</label>
                    <textarea class="textarea" id="p-ket" name="keterangan" rows="3" maxlength="255"><?= e((string) ($row['keterangan'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url($id === null ? 'project' : 'project/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Proyek</button>
    </div>
</form>
