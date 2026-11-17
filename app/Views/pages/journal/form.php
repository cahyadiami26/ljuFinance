<?php
/**
 * Form jurnal (baru / ubah draft).
 *
 * @var array|null $journal
 * @var array $header
 * @var array $lines
 * @var array $errors
 * @var bool $isEdit
 * @var array $akunPerTipe
 * @var array $bidangList
 * @var array $divisiList
 * @var array $projectList
 * @var array $ccList
 * @var array $partnerList
 * @var string $nomorPreview
 * @var array|null $periode
 */
use Lju\Services\JournalService;

$tipeLabels = [
    'aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'ekuitas' => 'Ekuitas',
    'pendapatan' => 'Pendapatan', 'beban' => 'Beban',
];

// opsi akun dibuat sekali, dipakai ulang di setiap baris
$akunOptions = '<option value="">— pilih akun —</option>';
foreach ($akunPerTipe as $tipe => $akunList) {
    $akunOptions .= '<optgroup label="' . e($tipeLabels[$tipe] ?? ucfirst((string) $tipe)) . '">';
    foreach ($akunList as $akun) {
        $akunOptions .= '<option value="' . (int) $akun['id'] . '">' . e($akun['kode'] . ' — ' . $akun['nama']) . '</option>';
    }
    $akunOptions .= '</optgroup>';
}

$renderDimensi = static function (int $index, array $line) use ($bidangList, $divisiList, $projectList, $ccList, $partnerList): string {
    $selects = [
        ['bidang_id', 'Bidang', $bidangList],
        ['divisi_id', 'Divisi', $divisiList],
        ['project_id', 'Proyek', $projectList],
        ['cost_center_id', 'Cost Center', $ccList],
        ['partner_id', 'Vendor/Customer', $partnerList],
    ];

    $html = '<div class="dimensi-grid">';
    foreach ($selects as [$name, $label, $options]) {
        $selected = (int) ($line[$name] ?? 0);
        $html .= '<label class="dimensi-item"><span>' . e($label) . '</span><select class="select select--sm" name="lines[' . $index . '][' . $name . ']">';
        $html .= '<option value="">—</option>';
        foreach ($options as $option) {
            $html .= '<option value="' . (int) $option['id'] . '"' . ($selected === (int) $option['id'] ? ' selected' : '') . '>' . e((string) $option['nama']) . '</option>';
        }
        $html .= '</select></label>';
    }
    $html .= '</div>';
    return $html;
};

$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
?>
<?php if (isset($errors['general']) || isset($errors['balance']) || isset($errors['lines'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Jurnal belum dapat disimpan</div>
            <?php if (isset($errors['general'])): ?><div class="small"><?= e((string) $errors['general']) ?></div><?php endif; ?>
            <?php if (isset($errors['balance'])): ?><div class="small"><?= e((string) $errors['balance']) ?></div><?php endif; ?>
            <?php if (isset($errors['lines'])): ?><div class="small"><?= e((string) $errors['lines']) ?></div><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? e(url('journal/' . (int) $journal['id'])) : e(url('journal')) ?>" id="journal-form" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Jurnal</h2>
                <div class="card-sub">Nomor dibuat otomatis berdasarkan tipe dan tanggal.</div>
            </div>
            <span class="pill"><span class="mono"><?= e($nomorPreview) ?></span></span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tanggal">Tanggal</label>
                    <input class="input" type="date" id="f-tanggal" name="tanggal" value="<?= e((string) $header['tanggal']) ?>" required>
                    <?php if ($periode !== null): ?>
                        <span class="field-help">
                            Periode: <?= e((string) $periode['nama']) ?> · <?= e((string) $periode['status'] === 'open' ? 'terbuka' : (string) $periode['status']) ?>
                        </span>
                    <?php endif; ?>
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field<?= $hasError('tipe') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tipe">Tipe Jurnal</label>
                    <select class="select" id="f-tipe" name="tipe">
                        <?php foreach (JournalService::TIPE as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $header['tipe'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="sumber" value="<?= e((string) $header['sumber']) ?>">
                    <?= $errorText('tipe') ?>
                </div>

                <div class="field<?= $hasError('referensi') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-referensi">Referensi Dokumen</label>
                    <input class="input" type="text" id="f-referensi" name="referensi" maxlength="80"
                           value="<?= e((string) $header['referensi']) ?>" placeholder="mis. PO-2026-0123 / Invoice vendor">
                    <?= $errorText('referensi') ?>
                </div>

                <div class="field field--full<?= $hasError('deskripsi') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-deskripsi">Deskripsi</label>
                    <input class="input" type="text" id="f-deskripsi" name="deskripsi" maxlength="255" required
                           value="<?= e((string) $header['deskripsi']) ?>"
                           placeholder="mis. Pencatatan tagihan vendor proyek gedung regional">
                    <?= $errorText('deskripsi') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Baris Jurnal</h2>
                <div class="card-sub">Isi debit <em>atau</em> kredit pada setiap baris. Total harus seimbang sebelum disimpan.</div>
            </div>
            <button class="btn btn--sm" type="button" data-line-add><?= icon('plus', 14) ?> Tambah Baris</button>
        </div>
        <div class="table-wrap">
            <table class="table journal-editor">
                <thead>
                    <tr>
                        <th style="width:38px" class="center">#</th>
                        <th style="min-width:240px">Akun</th>
                        <th>Keterangan</th>
                        <th style="width:140px" class="num">Debit</th>
                        <th style="width:140px" class="num">Kredit</th>
                        <th style="width:76px"></th>
                    </tr>
                </thead>
                <tbody id="journal-lines">
                    <?php foreach ($lines as $index => $line): ?>
                        <?php
                        $lineHasDimensi = (int) ($line['bidang_id'] ?? 0) + (int) ($line['divisi_id'] ?? 0) + (int) ($line['project_id'] ?? 0) + (int) ($line['cost_center_id'] ?? 0) + (int) ($line['partner_id'] ?? 0) > 0;
                        $debit = (float) ($line['debit'] ?? 0);
                        $kredit = (float) ($line['kredit'] ?? 0);
                        ?>
                        <tr class="jline" data-line>
                            <td class="center jline-no"><?= $index + 1 ?></td>
                            <td>
                                <select class="select select--sm" name="lines[<?= $index ?>][coa_id]" data-selected="<?= (int) ($line['coa_id'] ?? 0) ?>">
                                    <?= $akunOptions ?>
                                </select>
                            </td>
                            <td>
                                <input class="input input--sm" type="text" name="lines[<?= $index ?>][deskripsi]" maxlength="255"
                                       value="<?= e((string) ($line['deskripsi'] ?? '')) ?>" placeholder="Keterangan baris (opsional)">
                            </td>
                            <td>
                                <input class="input input--sm money" type="text" inputmode="numeric" data-money
                                       name="lines[<?= $index ?>][debit]" value="<?= $debit > 0 ? e(number_format($debit, 0, ',', '.')) : '' ?>" placeholder="0">
                            </td>
                            <td>
                                <input class="input input--sm money" type="text" inputmode="numeric" data-money
                                       name="lines[<?= $index ?>][kredit]" value="<?= $kredit > 0 ? e(number_format($kredit, 0, ',', '.')) : '' ?>" placeholder="0">
                            </td>
                            <td>
                                <div class="jline-actions">
                                    <button class="icon-btn<?= $lineHasDimensi ? ' is-active' : '' ?>" type="button" data-line-more
                                            title="Dimensi laporan"><?= icon('layers', 14) ?></button>
                                    <button class="icon-btn" type="button" data-line-remove title="Hapus baris"><?= icon('x', 14) ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr class="jline-dimensi" data-dimensi <?= $lineHasDimensi ? '' : 'hidden' ?>>
                            <td></td>
                            <td colspan="5"><?= $renderDimensi((int) $index, $line) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="right strong small">TOTAL</td>
                        <td class="num strong" id="total-debit">0</td>
                        <td class="num strong" id="total-kredit">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border)">
            <div class="balance-bar">
                <span class="balance-chip" id="balance-chip" data-state="empty"><?= icon('scale', 14) ?> <span>Menghitung…</span></span>
                <span class="tiny muted">Selisih debit–kredit harus Rp 0 sebelum jurnal dapat disimpan.</span>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:980px">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' ?></button>
        <a class="btn" href="<?= $isEdit ? e(url('journal/' . (int) $journal['id'])) : e(url('journal')) ?>">Batal</a>
        <span class="spacer"></span>
        <span class="tiny muted">Setelah tersimpan, jurnal masih berupa draft dan dapat diajukan untuk persetujuan.</span>
    </div>
</form>

<template id="journal-line-template">
    <tr class="jline" data-line>
        <td class="center jline-no"></td>
        <td>
            <select class="select select--sm" name="lines[__INDEX__][coa_id]"><?= $akunOptions ?></select>
        </td>
        <td>
            <input class="input input--sm" type="text" name="lines[__INDEX__][deskripsi]" maxlength="255" placeholder="Keterangan baris (opsional)">
        </td>
        <td>
            <input class="input input--sm money" type="text" inputmode="numeric" data-money name="lines[__INDEX__][debit]" placeholder="0">
        </td>
        <td>
            <input class="input input--sm money" type="text" inputmode="numeric" data-money name="lines[__INDEX__][kredit]" placeholder="0">
        </td>
        <td>
            <div class="jline-actions">
                <button class="icon-btn" type="button" data-line-more title="Dimensi laporan"><?= icon('layers', 14) ?></button>
                <button class="icon-btn" type="button" data-line-remove title="Hapus baris"><?= icon('x', 14) ?></button>
            </div>
        </td>
    </tr>
    <tr class="jline-dimensi" data-dimensi hidden>
        <td></td>
        <td colspan="5"><?= $renderDimensi(0, []) ?></td>
    </tr>
</template>

<script src="<?= e(asset('js/journal.js')) ?>" defer></script>
