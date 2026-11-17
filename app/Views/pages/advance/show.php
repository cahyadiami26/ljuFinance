<?php
/**
 * Detail uang muka kegiatan.
 *
 * @var array $row
 * @var array $items
 * @var float $totalItem
 * @var array $timeline
 * @var array $banks
 * @var array $coa
 * @var array $pajak
 * @var bool $canManage
 * @var bool $canApprove
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canCair
 * @var bool $canItems
 * @var bool $canSettle
 * @var bool $canDelete
 */
use Lju\Services\AdvanceService;

$status = (string) $row['status'];
$id = (int) $row['id'];
$diajukan = (float) $row['jumlah_diajukan'];
$disetujui = (float) $row['jumlah_disetujui'];
$dicairkan = (float) $row['jumlah_dicairkan'];
$dipertanggungjawabkan = (float) $row['jumlah_dipertanggungjawabkan'];
$sisa = $dicairkan - $dipertanggungjawabkan;

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
        $html .= '<option value="' . (int) $item['id'] . '"' . ((int) $selectedId === (int) $item['id'] ? ' selected' : '') . '>'
            . e((string) $item['nama']) . '</option>';
    }
    return $html;
};
$rows = $items;
if ($rows === []) {
    $rows = array_fill(0, 3, ['id' => 0, 'tanggal' => '', 'keterangan' => '', 'coa_id' => 0, 'jumlah' => 0, 'pajak_type_id' => null, 'no_bukti' => '']);
}
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($status) ?>
                <?php if (!empty($row['journal_nomor'])): ?>
                    <span class="badge badge--info"><?= icon('book', 11) ?> <?= e((string) $row['journal_nomor']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['keperluan']) ?></h2>
            <div class="card-sub">
                <?= e(tanggal($row['tanggal'], 'd M Y')) ?>
                · pemohon <?= e((string) ($row['pemohon_nama'] ?? '—')) ?>
                <?php if (!empty($row['bagian'])): ?> · <?= e((string) $row['bagian']) ?><?php endif; ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('advance/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('advance/' . $id . '/submit')) ?>" data-confirm="Ajukan uang muka ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('advance/' . $id . '/approve')) ?>" class="row" style="gap:6px"
                      data-confirm="Setujui pengajuan uang muka ini?">
                    <?= csrf_field() ?>
                    <input class="input input--sm money" type="text" inputmode="numeric" data-money name="jumlah_disetujui"
                           value="<?= $diajukan > 0 ? e(number_format($diajukan, 0, ',', '.')) : '' ?>" style="width:130px" title="Jumlah disetujui">
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove || $canDelete): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove): ?>
                            <form method="post" action="<?= e(url('advance/' . $id . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Tolak pengajuan</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('advance/' . $id . '/delete')) ?>" style="margin-top:12px"
                                  data-confirm="Hapus pengajuan ini secara permanen?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus Pengajuan</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Diajukan</div>
        <div class="summary-value"><?= e(rupiah($diajukan)) ?></div>
        <div class="tiny muted">Disetujui: <?= e(rupiah($disetujui)) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Dicairkan</div>
        <div class="summary-value"><?= e(rupiah($dicairkan)) ?></div>
        <div class="tiny muted"><?= !empty($row['tanggal_cair']) ? e(tanggal($row['tanggal_cair'], 'd M Y')) : 'Belum dicairkan' ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Dipertanggungjawabkan</div>
        <div class="summary-value"><?= e(rupiah($dipertanggungjawabkan)) ?></div>
        <div class="tiny muted"><?= count($items) ?> rincian biaya</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sisa Dana</div>
        <div class="summary-value <?= $sisa < 0 ? 'summary-value--down' : '' ?>"><?= e(rupiah($sisa)) ?></div>
        <div class="tiny muted"><?= $sisa > 0 ? 'Harus dikembalikan' : ($sisa < 0 ? 'Kurang bayar' : 'Tidak ada selisih') ?></div>
    </div>
</div>

<?php if ($canCair): ?>
    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Pencairan Dana</h2>
                <div class="card-sub">Jurnal otomatis: Debit Uang Muka Kegiatan, Kredit rekening bank.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('advance/' . $id . '/cair')) ?>" class="form-grid" data-confirm="Cairkan dana uang muka ini?">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="cair-bank">Rekening Sumber</label>
                    <select class="select" id="cair-bank" name="bank_account_id" required>
                        <option value="">— pilih rekening —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= (int) ($row['bank_account_id'] ?? 0) === (int) $bank['id'] ? 'selected' : '' ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama'] . ' (' . rupiah_short((float) $bank['saldo_berjalan']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="cair-tanggal">Tanggal Cair</label>
                    <input class="input" type="date" id="cair-tanggal" name="tanggal" value="<?= e(today()) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label" for="cair-jumlah">Jumlah Dicairkan</label>
                    <input class="input money" type="text" inputmode="numeric" id="cair-jumlah" name="jumlah" data-money
                           value="<?= $disetujui > 0 ? e(number_format($disetujui, 0, ',', '.')) : '' ?>" placeholder="0">
                    <span class="field-help">Kosongkan untuk memakai jumlah disetujui.</span>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <label class="field-label">&nbsp;</label>
                    <button class="btn btn--primary" type="submit"><?= icon('wallet', 15) ?> Cairkan Dana</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Pertanggungjawaban</h2>
            <div class="card-sub"><?= count($items) ?> rincian · total <?= e(rupiah($totalItem)) ?></div>
        </div>
        <?php if ($canItems): ?>
            <button class="btn btn--sm" type="button" data-rowset-add="advance-items"><?= icon('plus', 14) ?> Tambah Rincian</button>
        <?php endif; ?>
    </div>

    <?php if ($canItems): ?>
        <form method="post" action="<?= e(url('advance/' . $id . '/items')) ?>" data-no-spinner>
            <?= csrf_field() ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:120px">Tanggal</th>
                            <th>Keterangan</th>
                            <th style="min-width:200px">Akun Beban</th>
                            <th class="num" style="width:130px">Jumlah</th>
                            <th style="width:130px">Pajak</th>
                            <th style="width:130px">No. Bukti</th>
                            <th style="width:46px"></th>
                        </tr>
                    </thead>
                    <tbody data-rowset="advance-items">
                        <?php foreach ($rows as $index => $item): ?>
                            <tr data-row>
                                <td><input class="input input--sm" type="date" name="items[<?= $index ?>][tanggal]" value="<?= e((string) ($item['tanggal'] ?? '')) ?>"></td>
                                <td><input class="input input--sm" type="text" name="items[<?= $index ?>][keterangan]" maxlength="255" value="<?= e((string) ($item['keterangan'] ?? '')) ?>" placeholder="Uraian biaya" required></td>
                                <td>
                                    <select class="select select--sm" name="items[<?= $index ?>][coa_id]">
                                        <?= $coaOptions((int) ($item['coa_id'] ?? 0)) ?>
                                    </select>
                                </td>
                                <td><input class="input input--sm money" type="text" inputmode="numeric" data-money name="items[<?= $index ?>][jumlah]" value="<?= (float) ($item['jumlah'] ?? 0) > 0 ? e(number_format((float) $item['jumlah'], 0, ',', '.')) : '' ?>" placeholder="0"></td>
                                <td>
                                    <select class="select select--sm" name="items[<?= $index ?>][pajak_type_id]">
                                        <?= $pajakOptions($item['pajak_type_id'] ?? null) ?>
                                    </select>
                                </td>
                                <td><input class="input input--sm" type="text" name="items[<?= $index ?>][no_bukti]" maxlength="80" value="<?= e((string) ($item['no_bukti'] ?? '')) ?>" placeholder="Nota/faktur"></td>
                                <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus"><?= icon('x', 14) ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="border-top:1px solid var(--border);display:flex;gap:10px;align-items:center">
                <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Rincian</button>
                <span class="tiny muted">Rincian disimpan tanpa jurnal. Jurnal penyelesaian dibuat saat Anda menekan “Selesaikan”.</span>
            </div>
        </form>
    <?php elseif ($items === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('receipt', 22) ?></div>
            <h3>Belum ada rincian</h3>
            <p>Rincian pertanggungjawaban dapat diisi setelah dana dicairkan.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:110px">Tanggal</th>
                        <th>Keterangan</th>
                        <th style="width:220px">Akun</th>
                        <th style="width:130px">No. Bukti</th>
                        <th class="num" style="width:150px">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small"><?= !empty($item['tanggal']) ? e(tanggal($item['tanggal'], 'd M Y')) : '<span class="muted">—</span>' ?></td>
                            <td class="small"><?= e((string) $item['keterangan']) ?></td>
                            <td class="small"><span class="mono"><?= e((string) $item['coa_kode']) ?></span> · <?= e((string) $item['coa_nama']) ?></td>
                            <td class="small"><?= e((string) ($item['no_bukti'] ?? '—')) ?></td>
                            <td class="num"><?= e(rupiah((float) $item['jumlah'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="right strong small">TOTAL</td>
                        <td class="num strong"><?= e(rupiah($totalItem)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($canSettle): ?>
    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Penyelesaian Uang Muka</h2>
                <div class="card-sub">Membuat jurnal beban, menutup uang muka, dan mencatat pengembalian/kekurangan dana.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="notice notice--<?= abs($sisa) < 0.005 ? 'info' : 'warning' ?>" style="margin-bottom:14px">
                <?= icon('info', 16) ?>
                <div>
                    <?php if (abs($sisa) < 0.005): ?>
                        Dana terpakai sesuai dengan jumlah yang dicairkan.
                    <?php elseif ($sisa > 0): ?>
                        Sisa dana <strong><?= e(rupiah($sisa)) ?></strong> akan dicatat sebagai pengembalian ke rekening bank.
                    <?php else: ?>
                        Kekurangan dana <strong><?= e(rupiah(abs($sisa))) ?></strong> akan dicatat sebagai pengeluaran tambahan dari rekening bank.
                    <?php endif; ?>
                </div>
            </div>
            <form method="post" action="<?= e(url('advance/' . $id . '/settle')) ?>" class="form-grid" data-confirm="Selesaikan pertanggungjawaban dan posting jurnal?">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="settle-bank">Rekening</label>
                    <select class="select" id="settle-bank" name="bank_account_id" required>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= (int) ($row['bank_account_id'] ?? 0) === (int) $bank['id'] ? 'selected' : '' ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="settle-tanggal">Tanggal Penyelesaian</label>
                    <input class="input" type="date" id="settle-tanggal" name="tanggal" value="<?= e(today()) ?>" required>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <label class="field-label">&nbsp;</label>
                    <button class="btn btn--primary" type="submit"><?= icon('check-circle', 15) ?> Selesaikan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Informasi Pengajuan</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Nomor</dt><dd class="mono"><?= e((string) $row['nomor']) ?></dd>
                <dt>Tanggal</dt><dd><?= e(tanggal($row['tanggal'], 'd M Y')) ?></dd>
                <dt>Periode</dt><dd><?= e((string) ($row['periode_nama'] ?? '—')) ?></dd>
                <dt>Status</dt><dd><?= status_badge($status) ?></dd>
                <dt>Pemohon</dt><dd><?= e((string) ($row['pemohon_nama'] ?? '—')) ?><?= !empty($row['pemohon_jabatan']) ? ' · ' . e((string) $row['pemohon_jabatan']) : '' ?></dd>
                <dt>Bagian</dt><dd><?= e((string) ($row['bagian'] ?? '—')) ?></dd>
                <dt>Bidang / Divisi</dt><dd><?= e((string) ($row['bidang_nama'] ?? '—')) ?> · <?= e((string) ($row['divisi_nama'] ?? '—')) ?></dd>
                <dt>Proyek</dt><dd><?= e((string) ($row['project_nama'] ?? '—')) ?></dd>
                <dt>Cost Center</dt><dd><?= e((string) ($row['cost_center_nama'] ?? '—')) ?></dd>
                <dt>Rekening</dt><dd><?= e((string) ($row['bank_nama'] ?? '—')) ?></dd>
                <dt>Jurnal Cair</dt>
                <dd><?= !empty($row['journal_id']) ? '<a href="' . e(url('journal/' . (int) $row['journal_id'])) . '">' . e((string) $row['journal_nomor']) . '</a>' : '<span class="muted">—</span>' ?></dd>
                <dt>Jurnal Selesai</dt>
                <dd><?= !empty($row['journal_settlement_id']) ? '<a href="' . e(url('journal/' . (int) $row['journal_settlement_id'])) . '">' . e((string) $row['journal_settlement_nomor']) . '</a>' : '<span class="muted">—</span>' ?></dd>
                <?php if (!empty($row['catatan'])): ?>
                    <dt>Catatan</dt><dd><?= e((string) $row['catatan']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Riwayat Proses</h2>
        </div>
        <div class="card-body">
            <div class="timeline">
                <?php foreach ($timeline as $event): ?>
                    <div class="timeline-item">
                        <span class="timeline-dot timeline-dot--<?= e((string) $event['variant']) ?>"><?= icon((string) $event['icon'], 12) ?></span>
                        <div>
                            <div class="small strong"><?= e((string) $event['label']) ?></div>
                            <div class="tiny muted">
                                <?= !empty($event['at']) ? e(tanggal_waktu($event['at'])) : '—' ?>
                                <?php if (!empty($event['by'])): ?> · <?= e((string) $event['by']) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canItems): ?>
    <template id="advance-items-template">
        <tr data-row>
            <td><input class="input input--sm" type="date" name="items[__INDEX__][tanggal]"></td>
            <td><input class="input input--sm" type="text" name="items[__INDEX__][keterangan]" maxlength="255" placeholder="Uraian biaya" required></td>
            <td><select class="select select--sm" name="items[__INDEX__][coa_id]"><?= $coaOptions(0) ?></select></td>
            <td><input class="input input--sm money" type="text" inputmode="numeric" data-money name="items[__INDEX__][jumlah]" placeholder="0"></td>
            <td><select class="select select--sm" name="items[__INDEX__][pajak_type_id]"><?= $pajakOptions(null) ?></select></td>
            <td><input class="input input--sm" type="text" name="items[__INDEX__][no_bukti]" maxlength="80" placeholder="Nota/faktur"></td>
            <td class="center"><button class="icon-btn" type="button" data-row-remove title="Hapus"><?= icon('x', 14) ?></button></td>
        </tr>
    </template>
    <script src="<?= e(asset('js/transaksi.js')) ?>" defer></script>
<?php endif; ?>
