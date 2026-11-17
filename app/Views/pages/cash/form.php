<?php
/**
 * Form transaksi kas & bank.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $banks
 * @var array $coa
 * @var array $partners
 * @var array $projects
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 */
use Lju\Services\CashService;

$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';

$tipe = (string) ($row['tipe'] ?? 'keluar');
$selected = static function (string $field, int|string|null $value) use ($row): string {
    return (string) ($row[$field] ?? '') === (string) $value ? 'selected' : '';
};
$checked = static function (string $field, int|string|null $value) use ($row): string {
    return (string) ($row[$field] ?? '') === (string) $value ? 'checked' : '';
};
?>
<?php if (isset($errors['general'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Transaksi belum dapat disimpan</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('cash')) : e(url('cash/' . $id)) ?>" id="cash-form" data-no-spinner>
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Transaksi</h2>
                <div class="card-sub">Nomor dokumen dibuat otomatis: KM (masuk), KK (keluar), TRF (transfer).</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill"><span class="mono"><?= e((string) $row['nomor']) ?></span></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('tipe') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tipe">Tipe Transaksi</label>
                    <select class="select" id="f-tipe" name="tipe" data-cash-tipe>
                        <?php foreach (CashService::TIPE as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('tipe') ?>
                </div>

                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-tanggal">Tanggal</label>
                    <input class="input" type="date" id="f-tanggal" name="tanggal" required value="<?= e((string) $row['tanggal']) ?>">
                    <span class="field-help">Transaksi hanya dapat dicatat pada periode terbuka.</span>
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field<?= $hasError('bank_account_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-bank"><?= $tipe === 'transfer' ? 'Rekening Asal' : 'Rekening Kas / Bank' ?></label>
                    <select class="select" id="f-bank" name="bank_account_id" required>
                        <option value="">— pilih rekening —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= $selected('bank_account_id', (int) $bank['id']) ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama'] . ' (' . rupiah_short((float) $bank['saldo_berjalan']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('bank_account_id') ?>
                </div>

                <div class="field<?= $hasError('jumlah') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-jumlah">Jumlah (Rp)</label>
                    <input class="input money" type="text" inputmode="numeric" id="f-jumlah" name="jumlah" required
                           data-money value="<?= (float) $row['jumlah'] > 0 ? e(number_format((float) $row['jumlah'], 0, ',', '.')) : '' ?>" placeholder="0">
                    <?= $errorText('jumlah') ?>
                </div>

                <div class="field<?= $hasError('bank_account_tujuan_id') ? ' has-error' : '' ?>" data-cash-tipe-show="transfer">
                    <label class="field-label" for="f-bank-tujuan">Rekening Tujuan</label>
                    <select class="select" id="f-bank-tujuan" name="bank_account_tujuan_id">
                        <option value="">— pilih rekening tujuan —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= $selected('bank_account_tujuan_id', (int) $bank['id']) ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-help">Dana berpindah antar rekening, tanpa memengaruhi laba rugi.</span>
                    <?= $errorText('bank_account_tujuan_id') ?>
                </div>

                <div class="field<?= $hasError('coa_lawan_id') ? ' has-error' : '' ?>" data-cash-tipe-hide="transfer">
                    <label class="field-label" for="f-coa">Akun Lawan</label>
                    <select class="select" id="f-coa" name="coa_lawan_id">
                        <option value="">— pilih akun —</option>
                        <?php foreach ($coa as $akun): ?>
                            <option value="<?= (int) $akun['id'] ?>" <?= $selected('coa_lawan_id', (int) $akun['id']) ?>>
                                <?= e($akun['kode'] . ' — ' . $akun['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-help">Akun pendapatan/piutang untuk kas masuk, akun beban/utang untuk kas keluar.</span>
                    <?= $errorText('coa_lawan_id') ?>
                </div>

                <div class="field<?= $hasError('referensi') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-referensi">Referensi</label>
                    <input class="input" type="text" id="f-referensi" name="referensi" maxlength="80"
                           value="<?= e((string) ($row['referensi'] ?? '')) ?>" placeholder="mis. BKM-0123 / faktur vendor">
                    <?= $errorText('referensi') ?>
                </div>

                <div class="field<?= $hasError('partner_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-partner">Vendor / Customer</label>
                    <select class="select" id="f-partner" name="partner_id">
                        <option value="">—</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>" <?= $selected('partner_id', (int) $partner['id']) ?>>
                                <?= e($partner['nama'] . ' · ' . ucfirst((string) $partner['tipe'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('partner_id') ?>
                </div>

                <div class="field field--full<?= $hasError('deskripsi') ? ' has-error' : '' ?>">
                    <label class="field-label" for="f-deskripsi">Deskripsi</label>
                    <input class="input" type="text" id="f-deskripsi" name="deskripsi" maxlength="255" required
                           value="<?= e((string) ($row['deskripsi'] ?? '')) ?>" placeholder="mis. Pembayaran honor narasumber pelatihan">
                    <?= $errorText('deskripsi') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Dimensi Laporan</h2>
                <div class="card-sub">Opsional, dipakai untuk analisis per bidang, divisi, proyek, dan cost center.</div>
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

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Efek Jurnal</h2>
                <div class="card-sub">Jurnal dibuat otomatis saat transaksi diposting.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="notice">
                <?= icon('info', 16) ?>
                <div>
                    <strong>Kas masuk:</strong> Debit rekening bank, Kredit akun lawan.
                    <br><strong>Kas keluar:</strong> Debit akun lawan, Kredit rekening bank.
                    <br><strong>Transfer:</strong> Debit rekening tujuan, Kredit rekening asal.
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> <?= $id === null ? 'Simpan Draft' : 'Simpan Perubahan' ?></button>
        <a class="btn" href="<?= e($id === null ? url('cash') : url('cash/' . $id)) ?>">Batal</a>
        <span class="spacer"></span>
        <span class="tiny muted">Setelah tersimpan, transaksi masih draft dan perlu diajukan serta disetujui sebelum posting.</span>
    </div>
</form>

<script src="<?= e(asset('js/transaksi.js')) ?>" defer></script>
