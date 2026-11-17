<?php
/**
 * Detail pengajuan biaya.
 *
 * @var array $row
 * @var array $items
 * @var array $timeline
 * @var array $banks
 * @var bool $canManage
 * @var bool $canApprove
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canPay
 * @var bool $canDelete
 */
use Lju\Services\ExpenseService;

$status = (string) $row['status'];
$id = (int) $row['id'];
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= e(ExpenseService::TIPE[(string) $row['tipe']] ?? (string) $row['tipe']) ?></span>
                <?php if (!empty($row['journal_nomor'])): ?>
                    <span class="badge badge--info"><?= icon('book', 11) ?> <?= e((string) $row['journal_nomor']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['judul']) ?></h2>
            <div class="card-sub">
                <?= e(tanggal($row['tanggal'], 'd M Y')) ?>
                · pemohon <?= e((string) ($row['pemohon_nama'] ?? '—')) ?>
                <?php if (!empty($row['partner_nama'])): ?> · vendor <?= e((string) $row['partner_nama']) ?><?php endif; ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('expense/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('expense/' . $id . '/submit')) ?>" data-confirm="Ajukan pengajuan biaya ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('expense/' . $id . '/approve')) ?>" data-confirm="Setujui pengajuan biaya ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove || $canDelete): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove): ?>
                            <form method="post" action="<?= e(url('expense/' . $id . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Tolak pengajuan</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('expense/' . $id . '/delete')) ?>" style="margin-top:12px"
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

    <?php if (!empty($row['keterangan'])): ?>
        <div class="card-body" style="padding-top:0">
            <div class="notice" style="margin:0">
                <?= icon('info', 16) ?>
                <div><?= e((string) $row['keterangan']) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Subtotal</div>
        <div class="summary-value"><?= e(rupiah((float) $row['subtotal'])) ?></div>
        <div class="tiny muted"><?= count($items) ?> rincian biaya</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">PPN Masukan</div>
        <div class="summary-value"><?= e(rupiah((float) $row['ppn'])) ?></div>
        <div class="tiny muted">Dikreditkan ke akun PPN masukan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Potongan PPh</div>
        <div class="summary-value"><?= e(rupiah((float) $row['pph'])) ?></div>
        <div class="tiny muted">Utang pajak yang dipotong</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Total Dibayar</div>
        <div class="summary-value"><?= e(rupiah((float) $row['total'])) ?></div>
        <div class="tiny muted">Subtotal + PPN − PPh</div>
    </div>
</div>

<?php if ($canPay): ?>
    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Pembayaran</h2>
                <div class="card-sub">Jurnal otomatis: Debit beban + PPN masukan, Kredit utang PPh dan rekening bank.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('expense/' . $id . '/pay')) ?>" class="form-grid" data-confirm="Catat pembayaran dan posting jurnal?">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="pay-bank">Rekening Pembayaran</label>
                    <select class="select" id="pay-bank" name="bank_account_id" required>
                        <option value="">— pilih rekening —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>" <?= (int) ($row['bank_account_id'] ?? 0) === (int) $bank['id'] ? 'selected' : '' ?>>
                                <?= e($bank['kode'] . ' — ' . $bank['nama'] . ' (' . rupiah_short((float) $bank['saldo_berjalan']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="pay-tanggal">Tanggal Bayar</label>
                    <input class="input" type="date" id="pay-tanggal" name="tanggal" value="<?= e(today()) ?>" required>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <label class="field-label">&nbsp;</label>
                    <button class="btn btn--primary" type="submit"><?= icon('wallet', 15) ?> Bayar <?= e(rupiah((float) $row['total'])) ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Rincian Biaya</h2>
            <div class="card-sub"><?= count($items) ?> baris</div>
        </div>
    </div>
    <?php if ($items === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('receipt', 22) ?></div>
            <h3>Belum ada rincian</h3>
            <p>Ubah pengajuan untuk menambahkan rincian biaya.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th style="width:240px">Akun Beban</th>
                        <th style="width:130px">No. Bukti</th>
                        <th class="num" style="width:140px">Jumlah</th>
                        <th class="num" style="width:110px">PPN</th>
                        <th class="num" style="width:110px">PPh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small"><?= e((string) $item['keterangan']) ?></td>
                            <td class="small"><span class="mono"><?= e((string) $item['coa_kode']) ?></span> · <?= e((string) $item['coa_nama']) ?></td>
                            <td class="small"><?= e((string) ($item['no_bukti'] ?? '—')) ?></td>
                            <td class="num"><?= e(rupiah((float) $item['jumlah'])) ?></td>
                            <td class="num"><?= (float) $item['ppn'] > 0 ? e(rupiah((float) $item['ppn'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= (float) $item['pph'] > 0 ? e(rupiah((float) $item['pph'])) : '<span class="muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="right strong small">TOTAL</td>
                        <td class="num strong"><?= e(rupiah((float) $row['subtotal'])) ?></td>
                        <td class="num strong"><?= e(rupiah((float) $row['ppn'])) ?></td>
                        <td class="num strong"><?= e(rupiah((float) $row['pph'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

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
                <dt>Bidang / Divisi</dt><dd><?= e((string) ($row['bidang_nama'] ?? '—')) ?> · <?= e((string) ($row['divisi_nama'] ?? '—')) ?></dd>
                <dt>Proyek</dt><dd><?= e((string) ($row['project_nama'] ?? '—')) ?></dd>
                <dt>Cost Center</dt><dd><?= e((string) ($row['cost_center_nama'] ?? '—')) ?></dd>
                <dt>Rekening</dt><dd><?= e((string) ($row['bank_nama'] ?? '—')) ?></dd>
                <dt>Jurnal</dt>
                <dd><?= !empty($row['journal_id']) ? '<a href="' . e(url('journal/' . (int) $row['journal_id'])) . '">' . e((string) $row['journal_nomor']) . '</a>' : '<span class="muted">—</span>' ?></dd>
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
