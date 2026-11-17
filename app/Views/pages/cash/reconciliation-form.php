<?php
/**
 * Form rekonsiliasi bank baru.
 *
 * @var array $row
 * @var array $errors
 * @var array $banks
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';

$rows = $row['items'] ?? [];
if ($rows === []) {
    $rows = [['tanggal' => '', 'referensi' => '', 'keterangan' => '', 'debit' => 0, 'kredit' => 0]];
}
?>
<?php if (isset($errors['general'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Rekonsiliasi belum dapat dibuat</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('cash/reconciliation')) ?>" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Data Rekonsiliasi</h2>
                <div class="card-sub">Saldo buku dihitung otomatis dari jurnal posted sampai tanggal akhir.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('bank_account_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="r-bank">Rekening</label>
                    <select class="select" id="r-bank" name="bank_account_id" required>
                        <option value="">— pilih rekening —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= (int) $row['bank_account_id'] === (int) $bank['id'] ? 'selected' : '' ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('bank_account_id') ?>
                </div>
                <div class="field<?= $hasError('saldo_bank') ? ' has-error' : '' ?>">
                    <label class="field-label" for="r-saldo">Saldo Menurut Rekening Koran</label>
                    <input class="input money" type="text" inputmode="numeric" id="r-saldo" name="saldo_bank" data-money
                           value="<?= (float) $row['saldo_bank'] > 0 ? e(number_format((float) $row['saldo_bank'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <?= $errorText('saldo_bank') ?>
                </div>
                <div class="field<?= $hasError('tanggal_akhir') ? ' has-error' : '' ?>">
                    <label class="field-label" for="r-mulai">Tanggal Mulai</label>
                    <input class="input" type="date" id="r-mulai" name="tanggal_mulai" required value="<?= e((string) $row['tanggal_mulai']) ?>">
                </div>
                <div class="field<?= $hasError('tanggal_akhir') ? ' has-error' : '' ?>">
                    <label class="field-label" for="r-akhir">Tanggal Akhir</label>
                    <input class="input" type="date" id="r-akhir" name="tanggal_akhir" required value="<?= e((string) $row['tanggal_akhir']) ?>">
                    <?= $errorText('tanggal_akhir') ?>
                </div>
                <div class="field field--full">
                    <label class="field-label" for="r-catatan">Catatan</label>
                    <input class="input" type="text" id="r-catatan" name="catatan" maxlength="255"
                           value="<?= e((string) $row['catatan']) ?>" placeholder="mis. Rekening koran BNI periode September">
                </div>
            </div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Mutasi Rekening Koran (opsional)</h2>
                <div class="card-sub">Masukkan transaksi bank yang belum tercatat di buku agar dapat dicocokkan.</div>
            </div>
            <button class="btn btn--sm" type="button" data-rowset-add="recon-items"><?= icon('plus', 14) ?> Tambah Baris</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:130px">Tanggal</th>
                        <th style="width:150px">Referensi</th>
                        <th>Keterangan</th>
                        <th class="num" style="width:140px">Debit</th>
                        <th class="num" style="width:140px">Kredit</th>
                        <th style="width:46px"></th>
                    </tr>
                </thead>
                <tbody data-rowset="recon-items">
                    <?php foreach ($rows as $index => $item): ?>
                        <tr data-row>
                            <td><input class="input input--sm" type="date" name="items[<?= $index ?>][tanggal]" value="<?= e((string) ($item['tanggal'] ?? '')) ?>"></td>
                            <td><input class="input input--sm" type="text" name="items[<?= $index ?>][referensi]" maxlength="80" value="<?= e((string) ($item['referensi'] ?? '')) ?>" placeholder="No. referensi"></td>
                            <td><input class="input input--sm" type="text" name="items[<?= $index ?>][keterangan]" maxlength="255" value="<?= e((string) ($item['keterangan'] ?? '')) ?>" placeholder="Uraian mutasi"></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money name="items[<?= $index ?>][debit]" value="<?= (float) ($item['debit'] ?? 0) > 0 ? e(number_format((float) $item['debit'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money name="items[<?= $index ?>][kredit]" value="<?= (float) ($item['kredit'] ?? 0) > 0 ? e(number_format((float) $item['kredit'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                            <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus"><?= icon('x', 14) ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> Buat Rekonsiliasi</button>
        <a class="btn" href="<?= e(url('cash/reconciliation')) ?>">Batal</a>
        <span class="spacer"></span>
        <span class="tiny muted">Baris buku besar diambil otomatis dari jurnal pada rentang tanggal.</span>
    </div>
</form>

<template id="recon-items-template">
    <tr data-row>
        <td><input class="input input--sm" type="date" name="items[__INDEX__][tanggal]"></td>
        <td><input class="input input--sm" type="text" name="items[__INDEX__][referensi]" maxlength="80" placeholder="No. referensi"></td>
        <td><input class="input input--sm" type="text" name="items[__INDEX__][keterangan]" maxlength="255" placeholder="Uraian mutasi"></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money name="items[__INDEX__][debit]" placeholder="0"></td>
        <td><input class="input input--sm money" type="text" inputmode="numeric" data-money name="items[__INDEX__][kredit]" placeholder="0"></td>
        <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus"><?= icon('x', 14) ?></button></td>
    </tr>
</template>

<script src="<?= e(asset('js/transaksi.js')) ?>" defer></script>
