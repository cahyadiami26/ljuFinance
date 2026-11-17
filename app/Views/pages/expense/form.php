<?php
/**
 * Form pengajuan biaya.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $users
 * @var array $banks
 * @var array $coa
 * @var array $pajak
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 * @var array $projects
 * @var array $partners
 */
use Lju\Services\ExpenseService;

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
$pajakOptions = static function (?int $selectedId) use ($pajak): string {
    $html = '<option value="">Tanpa pajak</option>';
    foreach ($pajak as $item) {
        $html .= '<option value="' . (int) $item['id'] . '"'
            . ' data-jenis="' . e((string) $item['jenis']) . '"'
            . ' data-tarif="' . e((string) $item['tarif']) . '"'
            . ((int) $selectedId === (int) $item['id'] ? ' selected' : '') . '>'
            . e((string) $item['nama']) . '</option>';
    }
    return $html;
};

$items = $row['items'] ?? [];
if ($items === []) {
    $items = [['keterangan' => '', 'coa_id' => 0, 'jumlah' => 0, 'ppn' => 0, 'pph' => 0, 'pajak_type_id' => null, 'no_bukti' => '']];
}
?>
<?php if (isset($errors['general']) || isset($errors['items'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Pengajuan belum dapat disimpan</div>
            <?php if (isset($errors['general'])): ?><div class="small"><?= e((string) $errors['general']) ?></div><?php endif; ?>
            <?php if (isset($errors['items'])): ?><div class="small"><?= e((string) $errors['items']) ?></div><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('expense')) : e(url('expense/' . $id)) ?>" id="expense-form" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Pengajuan</h2>
                <div class="card-sub">Nomor dokumen EXP dibuat otomatis berdasarkan tanggal.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill"><span class="mono"><?= e((string) $row['nomor']) ?></span></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tanggal">Tanggal</label>
                    <input class="input" type="date" id="f-tanggal" name="tanggal" required value="<?= e((string) $row['tanggal']) ?>">
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field<?= $hasError('tipe') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tipe">Jenis Biaya</label>
                    <select class="select" id="f-tipe" name="tipe">
                        <?php foreach (ExpenseService::TIPE as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) $row['tipe'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('tipe') ?>
                </div>

                <div class="field field--full<?= $hasError('judul') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-judul">Judul Pengajuan</label>
                    <input class="input" type="text" id="f-judul" name="judul" maxlength="150" required
                           value="<?= e((string) $row['judul']) ?>" placeholder="mis. Konsumsi dan transportasi rapat koordinasi triwulan III">
                    <?= $errorText('judul') ?>
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
                    <label class="field-label" for="f-bank">Rekening Pembayaran (opsional)</label>
                    <select class="select" id="f-bank" name="bank_account_id">
                        <option value="">— dipilih saat pembayaran —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= $selected('bank_account_id', (int) $bank['id']) ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="f-partner">Vendor</label>
                    <select class="select" id="f-partner" name="partner_id">
                        <option value="">—</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>" <?= $selected('partner_id', (int) $partner['id']) ?>><?= e((string) $partner['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="f-keterangan">Keterangan</label>
                    <input class="input" type="text" id="f-keterangan" name="keterangan" maxlength="255"
                           value="<?= e((string) ($row['keterangan'] ?? '')) ?>" placeholder="Keterangan tambahan (opsional)">
                </div>
            </div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rincian Biaya</h2>
                <div class="card-sub">Setiap baris memakai akun beban yang boleh menerima posting.</div>
            </div>
            <button class="btn btn--sm" type="button" data-rowset-add="expense-items"><?= icon('plus', 14) ?> Tambah Baris</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:220px">Keterangan</th>
                        <th style="min-width:210px">Akun Beban</th>
                        <th class="num" style="width:130px">Jumlah</th>
                        <th style="width:150px">Pajak</th>
                        <th class="num" style="width:120px">PPN</th>
                        <th class="num" style="width:120px">PPh</th>
                        <th style="width:120px">No. Bukti</th>
                        <th style="width:46px"></th>
                    </tr>
                </thead>
                <tbody data-rowset="expense-items">
                    <?php foreach ($items as $index => $item): ?>
                        <tr data-row>
                            <td><input class="input input--sm" type="text" name="items[<?= $index ?>][keterangan]" maxlength="255" value="<?= e((string) ($item['keterangan'] ?? '')) ?>" placeholder="Uraian biaya" required></td>
                            <td><select class="select select--sm" name="items[<?= $index ?>][coa_id]"><?= $coaOptions((int) ($item['coa_id'] ?? 0)) ?></select></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-jumlah name="items[<?= $index ?>][jumlah]" value="<?= (float) ($item['jumlah'] ?? 0) > 0 ? e(number_format((float) $item['jumlah'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td><select class="select select--sm" name="items[<?= $index ?>][pajak_type_id]" data-item-pajak><?= $pajakOptions($item['pajak_type_id'] ?? null) ?></select></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-ppn name="items[<?= $index ?>][ppn]" value="<?= (float) ($item['ppn'] ?? 0) > 0 ? e(number_format((float) $item['ppn'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-pph name="items[<?= $index ?>][pph]" value="<?= (float) ($item['pph'] ?? 0) > 0 ? e(number_format((float) $item['pph'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td><input class="input input--sm" type="text" name="items[<?= $index ?>][no_bukti]" maxlength="80" value="<?= e((string) ($item['no_bukti'] ?? '')) ?>" placeholder="Nota/faktur"></td>
                            <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus baris"><?= icon('x', 14) ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="right strong small">SUBTOTAL</td>
                        <td class="num strong" data-expense-total="subtotal">0</td>
                        <td colspan="2" class="num small">PPN <span data-expense-total="ppn" class="strong">0</span></td>
                        <td class="num small">PPh <span data-expense-total="pph" class="strong">0</span></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border)">
            <div class="row" style="justify-content:space-between">
                <span class="tiny muted">Total dibayar = subtotal + PPN − PPh.</span>
                <div class="row" style="gap:10px">
                    <span class="summary-label" style="margin:0">Total</span>
                    <span class="summary-value" style="font-size:18px" data-expense-total="total">Rp 0</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Dimensi Laporan</h2>
                <div class="card-sub">Opsional, memudahkan analisis biaya per bidang, divisi, proyek, dan cost center.</div>
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
        <a class="btn" href="<?= e($id === null ? url('expense') : url('expense/' . $id)) ?>">Batal</a>
        <span class="spacer"></span>
        <span class="tiny muted">Jurnal pembayaran dibuat otomatis saat pembayaran dicatat.</span>
    </div>
</form>

<template id="expense-items-template">
    <tr data-row>
        <td><input class="input input--sm" type="text" name="items[__INDEX__][keterangan]" maxlength="255" placeholder="Uraian biaya" required></td>
        <td><select class="select select--sm" name="items[__INDEX__][coa_id]"><?= $coaOptions(0) ?></select></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-jumlah name="items[__INDEX__][jumlah]" placeholder="0"></td>
        <td><select class="select select--sm" name="items[__INDEX__][pajak_type_id]" data-item-pajak><?= $pajakOptions(null) ?></select></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-ppn name="items[__INDEX__][ppn]" placeholder="0"></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money data-item-pph name="items[__INDEX__][pph]" placeholder="0"></td>
        <td><input class="input input--sm" type="text" name="items[__INDEX__][no_bukti]" maxlength="80" placeholder="Nota/faktur"></td>
        <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus baris"><?= icon('x', 14) ?></button></td>
    </tr>
</template>

<script src="<?= e(asset('js/transaksi.js')) ?>" defer></script>
