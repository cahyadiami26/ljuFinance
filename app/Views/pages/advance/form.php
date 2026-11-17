<?php
/**
 * Form pengajuan uang muka.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $users
 * @var array $banks
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 * @var array $projects
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
            <div class="strong">Pengajuan belum dapat disimpan</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('advance')) : e(url('advance/' . $id)) ?>" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Pengajuan</h2>
                <div class="card-sub">Nomor dokumen CA dibuat otomatis. Ajukan setelah data lengkap.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill"><span class="mono"><?= e((string) $row['nomor']) ?></span></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tanggal">Tanggal Pengajuan</label>
                    <input class="input" type="date" id="f-tanggal" name="tanggal" required value="<?= e((string) $row['tanggal']) ?>">
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field<?= $hasError('pemohon_user_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-pemohon">Pemohon</label>
                    <select class="select" id="f-pemohon" name="pemohon_user_id" required>
                        <option value="">— pilih pemohon —</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" <?= $selected('pemohon_user_id', (int) $user['id']) ?>>
                                <?= e($user['name'] . (!empty($user['position']) ? ' · ' . $user['position'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('pemohon_user_id') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="f-bagian">Bagian</label>
                    <input class="input" type="text" id="f-bagian" name="bagian" maxlength="100"
                           value="<?= e((string) ($row['bagian'] ?? '')) ?>" placeholder="mis. Bidang Operasional">
                </div>

                <div class="field<?= $hasError('jumlah_diajukan') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-jumlah">Jumlah Diajukan (Rp)</label>
                    <input class="input money" type="text" inputmode="numeric" id="f-jumlah" name="jumlah_diajukan" required data-money
                           value="<?= (float) $row['jumlah_diajukan'] > 0 ? e(number_format((float) $row['jumlah_diajukan'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <?= $errorText('jumlah_diajukan') ?>
                </div>

                <div class="field field--full<?= $hasError('keperluan') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-keperluan">Keperluan</label>
                    <input class="input" type="text" id="f-keperluan" name="keperluan" maxlength="255" required
                           value="<?= e((string) ($row['keperluan'] ?? '')) ?>" placeholder="mis. Uang muka perjalanan dinas survei lokasi Bandar Lampung">
                    <?= $errorText('keperluan') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="f-bank">Rekening Pencairan (opsional)</label>
                    <select class="select" id="f-bank" name="bank_account_id">
                        <option value="">— dipilih saat pencairan —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= $selected('bank_account_id', (int) $bank['id']) ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="f-catatan">Catatan</label>
                    <input class="input" type="text" id="f-catatan" name="catatan" maxlength="255"
                           value="<?= e((string) ($row['catatan'] ?? '')) ?>" placeholder="Catatan tambahan">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Dimensi Laporan</h2>
                <div class="card-sub">Dipakai saat pencairan dan pertanggungjawaban.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
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
                    <label class="field-label" for="f-project">Proyek</label>
                    <select class="select" id="f-project" name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $selected('project_id', (int) $item['id']) ?>><?= e($item['kode'] . ' — ' . $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="f-cc">Cost Center</label>
                    <select class="select" id="f-cc" name="cost_center_id">
                        <option value="">—</option>
                        <?php foreach ($costCenters as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= $selected('cost_center_id', (int) $item['id']) ?>><?= e($item['kode'] . ' — ' . $item['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> <?= $id === null ? 'Simpan Pengajuan' : 'Simpan Perubahan' ?></button>
        <a class="btn" href="<?= e($id === null ? url('advance') : url('advance/' . $id)) ?>">Batal</a>
        <span class="spacer"></span>
        <span class="tiny muted">Setelah disimpan, ajukan untuk persetujuan; pencairan dilakukan setelah disetujui.</span>
    </div>
</form>
