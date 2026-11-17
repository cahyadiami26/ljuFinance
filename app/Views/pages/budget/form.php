<?php
/**
 * Form penyusunan RKAP.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $coaBeban
 * @var array $coaPendapatan
 * @var array $bidang
 * @var array $divisi
 * @var array $businessUnits
 * @var array $costCenters
 * @var array $projects
 * @var array $tahunList
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
$selected = static function (string $field, int|string|null $value) use ($row): string {
    return (string) ($row[$field] ?? '') === (string) $value ? 'selected' : '';
};

$akun = array_merge($coaPendapatan, $coaBeban);
usort($akun, static fn ($a, $b) => strcmp((string) $a['kode'], (string) $b['kode']));

$coaOptions = static function (int $selectedId) use ($akun): string {
    $html = '<option value="">— pilih akun —</option>';
    $html .= '<optgroup label="Pendapatan">';
    foreach ($akun as $item) {
        if ((string) ($item['tipe'] ?? 'pendapatan') !== 'pendapatan') {
            continue;
        }
        $html .= '<option value="' . (int) $item['id'] . '"' . ($selectedId === (int) $item['id'] ? ' selected' : '') . '>'
            . e($item['kode'] . ' — ' . $item['nama']) . '</option>';
    }
    $html .= '</optgroup><optgroup label="Beban">';
    foreach ($akun as $item) {
        if ((string) ($item['tipe'] ?? 'beban') !== 'beban') {
            continue;
        }
        $html .= '<option value="' . (int) $item['id'] . '"' . ($selectedId === (int) $item['id'] ? ' selected' : '') . '>'
            . e($item['kode'] . ' — ' . $item['nama']) . '</option>';
    }
    $html .= '</optgroup>';
    return $html;
};

$lines = $row['lines'] ?? [];
if ($lines === []) {
    $lines = [[
        'coa_id' => 0, 'jenis' => 'beban', 'bidang_id' => null, 'divisi_id' => null,
        'business_unit_id' => null, 'cost_center_id' => null, 'project_id' => null,
        'total' => 0, 'catatan' => '',
    ]];
}
?>
<?php if (isset($errors['general']) || isset($errors['lines'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">RKAP belum dapat disimpan</div>
            <?php if (isset($errors['general'])): ?><div class="small"><?= e((string) $errors['general']) ?></div><?php endif; ?>
            <?php if (isset($errors['lines'])): ?><div class="small"><?= e((string) $errors['lines']) ?></div><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('budget')) : e(url('budget/' . $id)) ?>" id="budget-form" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi RKAP</h2>
                <div class="card-sub">Nomor RKAP dibuat otomatis; revisi memakai tombol "Buat Revisi" pada detail.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill"><span class="mono"><?= e((string) $row['nomor']) ?></span> · versi <?= (int) $row['versi'] ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('tahun') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tahun">Tahun Anggaran</label>
                    <select class="select" id="f-tahun" name="tahun" required>
                        <?php foreach ($tahunList as $opsi): ?>
                            <option value="<?= (int) $opsi ?>" <?= (int) $row['tahun'] === (int) $opsi ? 'selected' : '' ?>><?= (int) $opsi ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('tahun') ?>
                </div>

                <div class="field field--full<?= $hasError('judul') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-judul">Judul RKAP</label>
                    <input class="input" type="text" id="f-judul" name="judul" maxlength="150" required
                           value="<?= e((string) $row['judul']) ?>" placeholder="mis. RKAP 2026 PT. Lampung Jasa Utama">
                    <?= $errorText('judul') ?>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="f-catatan">Catatan</label>
                    <input class="input" type="text" id="f-catatan" name="catatan" maxlength="255"
                           value="<?= e((string) ($row['catatan'] ?? '')) ?>" placeholder="Keterangan versi / dasar penyusunan (opsional)">
                </div>
            </div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Baris Anggaran</h2>
                <div class="card-sub">Jenis (pendapatan/beban) mengikuti tipe akun COA. Nilai tahunan disebar merata ke 12 bulan.</div>
            </div>
            <button class="btn btn--sm" type="button" data-rowset-add="budget-lines"><?= icon('plus', 14) ?> Tambah Baris</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:230px">Akun COA</th>
                        <th style="min-width:130px">Divisi</th>
                        <th style="min-width:130px">Bidang</th>
                        <th style="min-width:130px">Unit Usaha</th>
                        <th style="min-width:130px">Cost Center</th>
                        <th style="min-width:150px">Proyek</th>
                        <th class="num" style="width:150px">Pagu Tahunan</th>
                        <th style="min-width:120px">Catatan</th>
                        <th style="width:46px"></th>
                    </tr>
                </thead>
                <tbody data-rowset="budget-lines">
                    <?php foreach ($lines as $index => $line): ?>
                        <tr data-row>
                            <td><select class="select select--sm" name="lines[<?= $index ?>][coa_id]"><?= $coaOptions((int) ($line['coa_id'] ?? 0)) ?></select></td>
                            <td>
                                <select class="select select--sm" name="lines[<?= $index ?>][divisi_id]">
                                    <option value="">—</option>
                                    <?php foreach ($divisi as $item): ?>
                                        <option value="<?= (int) $item['id'] ?>" <?= (int) ($line['divisi_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="select select--sm" name="lines[<?= $index ?>][bidang_id]">
                                    <option value="">—</option>
                                    <?php foreach ($bidang as $item): ?>
                                        <option value="<?= (int) $item['id'] ?>" <?= (int) ($line['bidang_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="select select--sm" name="lines[<?= $index ?>][business_unit_id]">
                                    <option value="">—</option>
                                    <?php foreach ($businessUnits as $item): ?>
                                        <option value="<?= (int) $item['id'] ?>" <?= (int) ($line['business_unit_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="select select--sm" name="lines[<?= $index ?>][cost_center_id]">
                                    <option value="">—</option>
                                    <?php foreach ($costCenters as $item): ?>
                                        <option value="<?= (int) $item['id'] ?>" <?= (int) ($line['cost_center_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="select select--sm" name="lines[<?= $index ?>][project_id]">
                                    <option value="">—</option>
                                    <?php foreach ($projects as $item): ?>
                                        <option value="<?= (int) $item['id'] ?>" <?= (int) ($line['project_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-budget-jumlah name="lines[<?= $index ?>][total]"
                                       value="<?= (float) ($line['total'] ?? 0) > 0 ? e(number_format((float) $line['total'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td><input class="input input--sm" type="text" name="lines[<?= $index ?>][catatan]" maxlength="255" value="<?= e((string) ($line['catatan'] ?? '')) ?>" placeholder="Opsional"></td>
                            <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus baris"><?= icon('x', 14) ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="right strong small">TOTAL PAGU</td>
                        <td class="num strong" data-budget-total="total">Rp 0</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border)">
            <div class="row" style="justify-content:space-between">
                <span class="tiny muted">Komponen pendapatan dan beban dapat digabung dalam satu RKAP; total dihitung otomatis.</span>
                <span class="term-badge"><?= icon('chart', 12) ?> 12 bulan</span>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= $id === null ? e(url('budget')) : e(url('budget/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan RKAP</button>
    </div>
</form>

<template id="budget-lines-template">
    <tr data-row>
        <td><select class="select select--sm" name="lines[__INDEX__][coa_id]"><?= $coaOptions(0) ?></select></td>
        <td>
            <select class="select select--sm" name="lines[__INDEX__][divisi_id]">
                <option value="">—</option>
                <?php foreach ($divisi as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="select select--sm" name="lines[__INDEX__][bidang_id]">
                <option value="">—</option>
                <?php foreach ($bidang as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="select select--sm" name="lines[__INDEX__][business_unit_id]">
                <option value="">—</option>
                <?php foreach ($businessUnits as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="select select--sm" name="lines[__INDEX__][cost_center_id]">
                <option value="">—</option>
                <?php foreach ($costCenters as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="select select--sm" name="lines[__INDEX__][project_id]">
                <option value="">—</option>
                <?php foreach ($projects as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-budget-jumlah name="lines[__INDEX__][total]" placeholder="0"></td>
        <td><input class="input input--sm" type="text" name="lines[__INDEX__][catatan]" maxlength="255" placeholder="Opsional"></td>
        <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus baris"><?= icon('x', 14) ?></button></td>
    </tr>
</template>
