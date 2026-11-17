<?php
/**
 * Form akun COA.
 *
 * @var array|null $row
 * @var bool $isEdit
 * @var array $parents
 * @var array $tipeList
 * @var array $kategoriList
 * @var array $errors
 */

$action = $isEdit ? url('master/coa/' . (int) $row['id']) : url('master/coa');

$valueOf = static function (string $key, $default = '') use ($row) {
    if (old($key) !== null) {
        return old($key);
    }
    if ($row !== null && array_key_exists($key, $row)) {
        return $row[$key];
    }
    return $default;
};

$currentParent = (string) $valueOf('parent_id', '');
$currentTipe = (string) $valueOf('tipe', 'aset');
$currentKategori = (string) $valueOf('kategori', 'aset_lancar');
$currentNormal = (string) $valueOf('saldo_normal', 'D');
$currentArus = (string) $valueOf('arus_kas', 'operasi');
?>
<form class="form-card" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <div class="form-grid">
        <div class="field <?= isset($errors['kode']) ? 'has-error' : '' ?>">
            <label class="field-label" for="kode">Kode Akun <span class="req">*</span></label>
            <input class="input" id="kode" name="kode" value="<?= e((string) $valueOf('kode')) ?>" placeholder="1-1210" maxlength="20">
            <?php if (isset($errors['kode'])): ?><span class="field-error"><?= e($errors['kode']) ?></span>
            <?php else: ?><span class="field-help">Gunakan format bertingkat, contoh 1-1210.</span><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['nama']) ? 'has-error' : '' ?>">
            <label class="field-label" for="nama">Nama Akun <span class="req">*</span></label>
            <input class="input" id="nama" name="nama" value="<?= e((string) $valueOf('nama')) ?>" maxlength="120">
            <?php if (isset($errors['nama'])): ?><span class="field-error"><?= e($errors['nama']) ?></span><?php endif; ?>
        </div>

        <div class="field">
            <label class="field-label" for="tipe">Klasifikasi Akun <span class="req">*</span></label>
            <select class="select" id="tipe" name="tipe">
                <?php foreach ($tipeList as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $currentTipe === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="kategori">Kategori Laporan <span class="req">*</span></label>
            <select class="select" id="kategori" name="kategori">
                <?php foreach ($kategoriList as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $currentKategori === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="parent_id">Akun Induk</label>
            <select class="select" id="parent_id" name="parent_id">
                <option value="">— tanpa induk (level 1) —</option>
                <?php foreach ($parents as $parent): ?>
                    <?php if ($isEdit && (int) $parent['id'] === (int) $row['id']) { continue; } ?>
                    <option value="<?= (int) $parent['id'] ?>" <?= $currentParent === (string) $parent['id'] ? 'selected' : '' ?>>
                        <?= e(str_repeat('— ', max(0, (int) $parent['level'] - 1)) . $parent['kode'] . ' · ' . $parent['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="field-help">Level akun dihitung otomatis dari akun induk.</span>
        </div>

        <div class="field">
            <label class="field-label" for="saldo_normal">Saldo Normal <span class="req">*</span></label>
            <select class="select" id="saldo_normal" name="saldo_normal">
                <option value="D" <?= $currentNormal === 'D' ? 'selected' : '' ?>>Debit</option>
                <option value="K" <?= $currentNormal === 'K' ? 'selected' : '' ?>>Kredit</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="arus_kas">Klasifikasi Arus Kas</label>
            <select class="select" id="arus_kas" name="arus_kas">
                <?php foreach (['operasi' => 'Aktivitas Operasi', 'investasi' => 'Aktivitas Investasi', 'pendanaan' => 'Aktivitas Pendanaan', 'non_kas' => 'Non-kas'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $currentArus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field-label">Pengaturan Akun</label>
            <div class="stack-8">
                <label class="checkbox">
                    <input type="checkbox" name="allow_posting" value="1" <?= (int) $valueOf('allow_posting', 1) === 1 ? 'checked' : '' ?>>
                    Boleh dipakai posting jurnal (bukan akun header)
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_kas_bank" value="1" <?= (int) $valueOf('is_kas_bank', 0) === 1 ? 'checked' : '' ?>>
                    Akun kas / bank
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_terkontrol" value="1" <?= (int) $valueOf('is_terkontrol', 0) === 1 ? 'checked' : '' ?>>
                    Dikendalikan modul lain (AR/AP/aset/pajak)
                </label>
            </div>
        </div>

        <div class="field field--full">
            <label class="field-label" for="deskripsi">Deskripsi</label>
            <textarea class="textarea" id="deskripsi" name="deskripsi" maxlength="255"><?= e((string) $valueOf('deskripsi')) ?></textarea>
        </div>

        <div class="field field--full">
            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" <?= (int) $valueOf('is_active', 1) === 1 ? 'checked' : '' ?>>
                Akun aktif dan dapat dipakai pada transaksi baru
            </label>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Akun' ?></button>
        <a class="btn" href="<?= e(url('master/coa')) ?>">Batal</a>
    </div>
</form>
