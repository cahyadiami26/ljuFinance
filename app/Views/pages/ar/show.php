<?php
/**
 * Detail invoice pelanggan (AR).
 *
 * @var array $row
 * @var array $items
 * @var array $payments
 * @var array $timeline
 * @var array $banks
 * @var array $metode
 * @var string $statusEfektif
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canApprove
 * @var bool $canPost
 * @var bool $canPay
 * @var bool $canDelete
 */
use Lju\Services\ArService;

$status = (string) $row['status'];
$id = (int) $row['id'];
$sisa = (float) $row['sisa'];
$hariTerlambat = $sisa > 0 ? (int) floor((strtotime(today()) - strtotime((string) $row['tanggal_jatuh_tempo'])) / 86400) : 0;
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($statusEfektif) ?>
                <?php if (!empty($row['contract_nomor'])): ?>
                    <span class="badge badge--info">Kontrak <?= e((string) $row['contract_nomor']) ?></span>
                <?php endif; ?>
                <?php if (!empty($row['journal_nomor'])): ?>
                    <span class="badge badge--info"><?= icon('book', 11) ?> <?= e((string) $row['journal_nomor']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) ($row['partner_nama'] ?? 'Pelanggan')) ?></h2>
            <div class="card-sub">
                <?= e((string) $row['deskripsi']) ?>
            </div>
            <div class="card-sub">
                Invoice <?= e(tanggal($row['tanggal'], 'd M Y')) ?>
                · jatuh tempo <?= e(tanggal($row['tanggal_jatuh_tempo'], 'd M Y')) ?>
                <?php if ($hariTerlambat > 0 && $sisa > 0): ?>
                    · <span class="amt-neg">terlambat <?= $hariTerlambat ?> hari</span>
                <?php endif; ?>
                <?php if (!empty($row['project_nama'])): ?> · <?= e((string) $row['project_nama']) ?><?php endif; ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('ar/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('ar/' . $id . '/submit')) ?>" data-confirm="Ajukan invoice ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('ar/' . $id . '/approve')) ?>" data-confirm="Setujui invoice pelanggan ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canPost): ?>
                <form method="post" action="<?= e(url('ar/' . $id . '/post')) ?>" data-confirm="Posting invoice ini ke jurnal?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('book', 14) ?> Posting</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove || $canDelete): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove): ?>
                            <form method="post" action="<?= e(url('ar/' . $id . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Kembalikan ke draft</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('ar/' . $id . '/delete')) ?>" style="margin-top:12px"
                                  data-confirm="Hapus invoice ini secara permanen?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus Invoice</button>
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
        <div class="summary-label">Subtotal</div>
        <div class="summary-value"><?= e(rupiah((float) $row['subtotal'])) ?></div>
        <div class="tiny muted"><?= count($items) ?> rincian pendapatan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">PPN Keluaran</div>
        <div class="summary-value"><?= e(rupiah((float) $row['ppn'])) ?></div>
        <div class="tiny muted">Dikreditkan ke utang PPN</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sudah Diterima</div>
        <div class="summary-value summary-value--up"><?= e(rupiah((float) $row['dibayar'])) ?></div>
        <div class="tiny muted"><?= count($payments) ?> penerimaan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sisa Piutang</div>
        <div class="summary-value<?= $sisa > 0 ? ' summary-value--down' : '' ?>"><?= e(rupiah($sisa)) ?></div>
        <div class="tiny muted">Dari total <?= e(rupiah((float) $row['total'])) ?></div>
    </div>
</div>

<?php if ($canPay): ?>
    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Penerimaan Pembayaran</h2>
                <div class="card-sub">Jurnal otomatis: Debit rekening bank + PPh dibayar dimuka, Kredit Piutang Usaha.</div>
            </div>
            <span class="term-badge"><?= icon('wallet', 12) ?> Sisa <?= e(rupiah($sisa)) ?></span>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('ar/' . $id . '/terima')) ?>" class="form-grid" data-confirm="Catat penerimaan piutang dan posting jurnal?">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="pay-bank">Rekening Penerimaan</label>
                    <select class="select" id="pay-bank" name="bank_account_id" required>
                        <option value="">— pilih rekening —</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?= (int) $bank['id'] ?>">
                                <?= e($bank['kode'] . ' — ' . $bank['nama'] . ' (' . rupiah_short((float) $bank['saldo_berjalan']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="pay-tanggal">Tanggal Terima</label>
                    <input class="input" type="date" id="pay-tanggal" name="tanggal" value="<?= e(today()) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label" for="pay-jumlah">Jumlah Diterima</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="pay-jumlah" name="jumlah" required
                           value="<?= e(number_format($sisa, 0, ',', '.')) ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="pay-pph">PPh Dipotong Pelanggan</label>
                    <input class="input money" type="text" inputmode="numeric" data-money id="pay-pph" name="potongan_pph" placeholder="0">
                </div>
                <div class="field">
                    <label class="field-label" for="pay-metode">Metode</label>
                    <select class="select" id="pay-metode" name="metode">
                        <?php foreach ($metode as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="pay-referensi">Referensi</label>
                    <input class="input" type="text" id="pay-referensi" name="referensi" maxlength="80" placeholder="No. transfer / cek">
                </div>
                <div class="field field--full" style="align-items:flex-end">
                    <button class="btn btn--primary" type="submit"><?= icon('wallet', 15) ?> Catat Penerimaan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Rincian Pendapatan</h2>
            <div class="card-sub"><?= count($items) ?> baris · PPN keluaran per baris</div>
        </div>
    </div>
    <?php if ($items === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('receipt', 22) ?></div>
            <h3>Belum ada rincian</h3>
            <p>Ubah invoice untuk menambahkan rincian pendapatan.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th style="width:260px">Akun Pendapatan</th>
                        <th class="num" style="width:150px">Jumlah</th>
                        <th class="num" style="width:130px">PPN</th>
                        <th class="num" style="width:150px">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small"><?= e((string) $item['keterangan']) ?></td>
                            <td class="small"><span class="mono"><?= e((string) $item['coa_kode']) ?></span> · <?= e((string) $item['coa_nama']) ?></td>
                            <td class="num"><?= e(rupiah((float) $item['jumlah'])) ?></td>
                            <td class="num"><?= (float) $item['ppn'] > 0 ? e(rupiah((float) $item['ppn'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num strong"><?= e(rupiah((float) $item['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="right strong small">TOTAL INVOICE</td>
                        <td class="num strong"><?= e(rupiah((float) $row['subtotal'])) ?></td>
                        <td class="num strong"><?= e(rupiah((float) $row['ppn'])) ?></td>
                        <td class="num strong"><?= e(rupiah((float) $row['total'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Riwayat Penerimaan</h2>
            <div class="card-sub"><?= count($payments) ?> penerimaan tercatat</div>
        </div>
    </div>
    <?php if ($payments === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('coins', 22) ?></div>
            <h3>Belum ada penerimaan</h3>
            <p>Penerimaan dapat dicatat setelah invoice diposting.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:170px">Nomor</th>
                        <th style="width:110px">Tanggal</th>
                        <th style="width:200px">Rekening</th>
                        <th style="width:110px">Metode</th>
                        <th class="num" style="width:140px">Jumlah</th>
                        <th class="num" style="width:120px">PPh Dipotong</th>
                        <th style="width:140px">Jurnal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $payment['nomor']) ?></td>
                            <td class="small"><?= e(tanggal($payment['tanggal'], 'd M Y')) ?></td>
                            <td class="small"><?= e((string) ($payment['bank_nama'] ?? '—')) ?></td>
                            <td class="small"><?= e(ArService::METODE[(string) $payment['metode']] ?? (string) $payment['metode']) ?></td>
                            <td class="num strong"><?= e(rupiah((float) $payment['jumlah'])) ?></td>
                            <td class="num"><?= (float) $payment['potongan_pph'] > 0 ? e(rupiah((float) $payment['potongan_pph'])) : '<span class="muted">—</span>' ?></td>
                            <td class="small">
                                <?php if (!empty($payment['journal_id'])): ?>
                                    <a class="mono" style="font-size:11.5px" href="<?= e(url('journal/' . (int) $payment['journal_id'])) ?>"><?= e((string) $payment['journal_nomor']) ?></a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Informasi Invoice</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Nomor</dt><dd class="mono"><?= e((string) $row['nomor']) ?></dd>
                <dt>Pelanggan</dt><dd><?= e((string) ($row['partner_nama'] ?? '—')) ?></dd>
                <dt>NPWP</dt><dd class="mono"><?= e((string) ($row['npwp'] ?? '—')) ?></dd>
                <dt>Kontrak</dt><dd><?= !empty($row['contract_nomor']) ? e((string) $row['contract_nomor']) . ' — ' . e((string) ($row['contract_judul'] ?? '')) : '—' ?></dd>
                <dt>Tanggal</dt><dd><?= e(tanggal($row['tanggal'], 'd M Y')) ?></dd>
                <dt>Jatuh Tempo</dt><dd><?= e(tanggal($row['tanggal_jatuh_tempo'], 'd M Y')) ?> · <?= (int) $row['termin_hari'] ?> hari</dd>
                <dt>Proyek</dt><dd><?= e((string) ($row['project_nama'] ?? '—')) ?></dd>
                <dt>Business Unit</dt><dd><?= e((string) ($row['business_unit_nama'] ?? '—')) ?></dd>
                <dt>Bidang / Divisi</dt><dd><?= e((string) ($row['bidang_nama'] ?? '—')) ?> · <?= e((string) ($row['divisi_nama'] ?? '—')) ?></dd>
                <dt>Cost Center</dt><dd><?= e((string) ($row['cost_center_nama'] ?? '—')) ?></dd>
                <dt>Jurnal</dt>
                <dd><?= !empty($row['journal_id']) ? '<a href="' . e(url('journal/' . (int) $row['journal_id'])) . '">' . e((string) $row['journal_nomor']) . '</a>' : '<span class="muted">—</span>' ?></dd>
                <dt>Dibuat oleh</dt><dd><?= e((string) ($row['created_by_name'] ?? '—')) ?></dd>
                <dt>Disetujui oleh</dt><dd><?= e((string) ($row['approved_by_name'] ?? '—')) ?><?= !empty($row['approved_at']) ? ' · ' . e(tanggal_waktu($row['approved_at'])) : '' ?></dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Riwayat Proses</h2>
        </div>
        <div class="card-body">
            <?php if ($timeline === []): ?>
                <p class="small muted">Belum ada riwayat proses.</p>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>
</div>
