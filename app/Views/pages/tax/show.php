<?php
/**
 * Detail dokumen pajak.
 *
 * @var array $row
 * @var array $banks
 * @var array $tipeList
 * @var array $jenisList
 * @var array $statusList
 * @var bool $canEdit
 * @var bool $canSetor
 * @var bool $canPost
 * @var bool $canLapor
 * @var bool $canVoid
 * @var bool $canDelete
 */
$status = (string) $row['status'];
$id = (int) $row['id'];
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= e((string) ($tipeList[(string) $row['tipe']] ?? $row['tipe'])) ?></span>
                <?php if (!empty($row['journal_nomor'])): ?>
                    <a class="badge badge--info" href="<?= e(url('journal/' . (int) $row['journal_id'])) ?>"><?= icon('book', 11) ?> <?= e((string) $row['journal_nomor']) ?></a>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['tax_nama']) ?></h2>
            <div class="card-sub">
                <?= e(tanggal($row['tanggal'], 'd M Y')) ?>
                · <?= e((string) ($row['partner_nama'] ?? 'Tanpa partner')) ?>
                · <?= e((string) ($row['periode_nama'] ?? '—')) ?>
            </div>
        </div>
        <div class="action-buttons">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('tax/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>
            <?php if ($canPost): ?>
                <form method="post" action="<?= e(url('tax/' . $id . '/post')) ?>" data-confirm="Posting dokumen pajak ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm" type="submit"><?= icon('check-circle', 14) ?> Posting</button>
                </form>
            <?php endif; ?>
            <?php if ($canLapor): ?>
                <form method="post" action="<?= e(url('tax/' . $id . '/lapor')) ?>" data-confirm="Tandai dokumen ini sudah dilaporkan ke DJP?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm" type="submit"><?= icon('check', 14) ?> Tandai Dilaporkan</button>
                </form>
            <?php endif; ?>
            <?php if ($canVoid): ?>
                <form method="post" action="<?= e(url('tax/' . $id . '/void')) ?>" data-confirm="Void dokumen pajak ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" type="submit"><?= icon('x', 14) ?> Void</button>
                </form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
                <form method="post" action="<?= e(url('tax/' . $id . '/delete')) ?>" data-confirm="Hapus dokumen pajak draft ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">DPP</div>
        <div class="summary-value"><?= e(rupiah((float) $row['dpp'])) ?></div>
        <div class="tiny muted">Dasar pengenaan pajak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Tarif</div>
        <div class="summary-value"><?= e(number_format((float) $row['tarif'], 2, ',', '.')) ?>%</div>
        <div class="tiny muted"><?= e((string) ($jenisList[(string) $row['jenis']] ?? $row['jenis'])) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Pajak</div>
        <div class="summary-value summary-value--down"><?= e(rupiah((float) $row['nilai_pajak'])) ?></div>
        <div class="tiny muted">Pajak terutang/disetor</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Status</div>
        <div class="summary-value" style="font-size:14px"><?= status_badge($status) ?></div>
        <div class="tiny muted"><?= e((string) ($row['keterangan'] ?? '')) ?></div>
    </div>
</div>

<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Informasi Dokumen</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Nomor</dt><dd class="mono"><?= e((string) $row['nomor']) ?></dd>
                <dt>Tipe</dt><dd><?= e((string) ($tipeList[(string) $row['tipe']] ?? $row['tipe'])) ?></dd>
                <dt>Jenis Pajak</dt><dd><?= e((string) $row['tax_kode'] . ' — ' . (string) $row['tax_nama']) ?></dd>
                <dt>Partner</dt><dd><?= e((string) ($row['partner_nama'] ?? '—')) ?></dd>
                <dt>NPWP</dt><dd class="mono"><?= e((string) ($row['npwp'] ?? '—')) ?></dd>
                <dt>Tanggal</dt><dd><?= e(tanggal($row['tanggal'], 'd M Y')) ?></dd>
                <dt>Periode</dt><dd><?= e((string) ($row['periode_nama'] ?? '—')) ?></dd>
                <dt>Referensi Modul</dt>
                <dd><?= $row['ref_module'] !== null ? e((string) $row['ref_module'] . ' #' . (string) $row['ref_id']) : '—' ?></dd>
                <dt>Dibuat</dt><dd><?= e((string) ($row['created_by_name'] ?? '—')) ?> · <?= e(tanggal_waktu($row['created_at'])) ?></dd>
            </dl>
        </div>
    </div>

    <div>
        <?php if ($canSetor): ?>
            <div class="card mb-16">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">Setorkan Pajak</h2>
                        <div class="card-sub">Jurnal D utang pajak / K bank</div>
                    </div>
                </div>
                <form method="post" action="<?= e(url('tax/' . $id . '/setor')) ?>" data-confirm="Catat penyetoran pajak ini?">
                    <?= csrf_field() ?>
                    <div class="card-body">
                        <div class="field">
                            <label class="field-label" for="setor-bank">Rekening Bank</label>
                            <select class="select" id="setor-bank" name="bank_account_id" required>
                                <option value="">— pilih rekening —</option>
                                <?php foreach ($banks as $bank): ?>
                                    <option value="<?= (int) $bank['id'] ?>"><?= e($bank['nama'] . ' · ' . $bank['nomor_rekening']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-foot row" style="justify-content:flex-end">
                        <button class="btn btn--primary" type="submit"><?= icon('check', 15) ?> Setor <?= e(rupiah((float) $row['nilai_pajak'])) ?></button>
                    </div>
                </form>
            </div>
        <?php elseif ((string) $row['tipe'] !== 'ssp'): ?>
            <div class="notice notice--info mb-16">
                <?= icon('info', 16) ?>
                <div>Dokumen ini merupakan bukti pajak dari transaksi AP/AR/biaya. Jurnal terkait sudah dibuat di modul asal; tidak ada jurnal tambahan saat posting.</div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Alur Dokumen</h2>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <span class="timeline-dot timeline-dot--primary"><?= icon('edit', 12) ?></span>
                        <div>
                            <div class="small strong">Dokumen dicatat</div>
                            <div class="tiny muted"><?= e(tanggal_waktu($row['created_at'])) ?> · status draft</div>
                        </div>
                    </div>
                    <?php if ((string) $row['tipe'] === 'ssp' && in_array($status, ['posted', 'reported'], true)): ?>
                        <div class="timeline-item">
                            <span class="timeline-dot timeline-dot--sukses"><?= icon('check', 12) ?></span>
                            <div>
                                <div class="small strong">Pajak disetorkan</div>
                                <div class="tiny muted">Jurnal <?= e((string) ($row['journal_nomor'] ?? '—')) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($status === 'reported'): ?>
                        <div class="timeline-item">
                            <span class="timeline-dot timeline-dot--info"><?= icon('receipt', 12) ?></span>
                            <div>
                                <div class="small strong">Dilaporkan ke DJP</div>
                                <div class="tiny muted">Tercatat pada rekap SPT</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($status === 'void'): ?>
                        <div class="timeline-item">
                            <span class="timeline-dot timeline-dot--bahaya"><?= icon('x', 12) ?></span>
                            <div>
                                <div class="small strong">Dokumen di-void</div>
                                <div class="tiny muted">Tidak dihitung pada rekap pajak</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
