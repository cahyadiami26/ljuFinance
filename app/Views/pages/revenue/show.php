<?php
/**
 * Detail pengakuan pendapatan kontrak.
 *
 * @var array $row
 * @var array $timeline
 * @var array $metode
 * @var float $terakui
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canApprove
 * @var bool $canPost
 * @var bool $canDelete
 */
use Lju\Services\RevenueService;

$status = (string) $row['status'];
$id = (int) $row['id'];
$nilaiKontrak = (float) ($row['nilai_kontrak'] ?? 0);
$persentase = (float) ($row['persentase'] ?? 0);
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= icon('chart', 11) ?> <?= e($metode[(string) $row['metode']] ?? (string) $row['metode']) ?></span>
                <?php if (!empty($row['journal_nomor'])): ?>
                    <span class="badge badge--info"><?= icon('book', 11) ?> <?= e((string) $row['journal_nomor']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) ($row['contract_judul'] ?? $row['project_nama'] ?? 'Pengakuan Pendapatan')) ?></h2>
            <div class="card-sub">
                <?= !empty($row['contract_nomor']) ? 'Kontrak ' . e((string) $row['contract_nomor']) . ' · ' : '' ?>
                <?= e((string) ($row['partner_nama'] ?? '')) ?>
                · periode <?= e((string) ($row['periode_nama'] ?? '—')) ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('revenue/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('revenue/' . $id . '/submit')) ?>" data-confirm="Ajukan pengakuan pendapatan ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('revenue/' . $id . '/approve')) ?>" data-confirm="Setujui pengakuan pendapatan ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canPost): ?>
                <form method="post" action="<?= e(url('revenue/' . $id . '/post')) ?>" data-confirm="Posting pengakuan ini ke jurnal?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('book', 14) ?> Posting</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove || $canDelete): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove): ?>
                            <form method="post" action="<?= e(url('revenue/' . $id . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Kembalikan ke draft</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('revenue/' . $id . '/delete')) ?>" style="margin-top:12px"
                                  data-confirm="Hapus dokumen pengakuan ini?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus Dokumen</button>
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
        <div class="summary-label">Nilai Pengakuan</div>
        <div class="summary-value"><?= e(rupiah((float) $row['nilai'])) ?></div>
        <div class="tiny muted"><?= e($metode[(string) $row['metode']] ?? '') ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Kontrak</div>
        <div class="summary-value"><?= $nilaiKontrak > 0 ? e(rupiah($nilaiKontrak)) : '<span class="muted">—</span>' ?></div>
        <div class="tiny muted">Total kontrak terkait</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Total Diakui (Posted)</div>
        <div class="summary-value"><?= e(rupiah($terakui + ($status === 'posted' ? (float) $row['nilai'] : 0))) ?></div>
        <div class="tiny muted">Akumulasi kontrak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Persentase</div>
        <div class="summary-value"><?= e(number_format($persentase, 2, ',', '.')) ?>%</div>
        <div class="tiny muted">Progres penyelesaian</div>
    </div>
</div>

<?php if ($nilaiKontrak > 0): ?>
    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Progres Pengakuan Kontrak</h2>
                <div class="card-sub">Akumulasi nilai yang sudah diakui terhadap nilai kontrak.</div>
            </div>
            <span class="pill"><?= e(rupiah($terakui + ($status === 'posted' ? (float) $row['nilai'] : 0))) ?> / <?= e(rupiah($nilaiKontrak)) ?></span>
        </div>
        <div class="card-body">
            <div class="progress-track" style="height:8px">
                <span style="width:<?= (int) min(100, ($terakui + ($status === 'posted' ? (float) $row['nilai'] : 0)) / $nilaiKontrak * 100) ?>%"></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Informasi Dokumen</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Nomor</dt><dd class="mono"><?= e((string) $row['nomor']) ?></dd>
                <dt>Tanggal</dt><dd><?= e(tanggal($row['tanggal'], 'd M Y')) ?></dd>
                <dt>Periode</dt><dd><?= e((string) ($row['periode_nama'] ?? '—')) ?></dd>
                <dt>Metode</dt><dd><?= e($metode[(string) $row['metode']] ?? (string) $row['metode']) ?></dd>
                <dt>Kontrak</dt><dd><?= !empty($row['contract_nomor']) ? e((string) $row['contract_nomor']) . ' — ' . e((string) ($row['contract_judul'] ?? '')) : '—' ?></dd>
                <dt>Pelanggan</dt><dd><?= e((string) ($row['partner_nama'] ?? '—')) ?></dd>
                <dt>Proyek</dt><dd><?= e((string) ($row['project_nama'] ?? '—')) ?></dd>
                <dt>Jurnal</dt>
                <dd><?= !empty($row['journal_id']) ? '<a href="' . e(url('journal/' . (int) $row['journal_id'])) . '">' . e((string) $row['journal_nomor']) . '</a>' : '<span class="muted">—</span>' ?></dd>
                <dt>Dibuat oleh</dt><dd><?= e((string) ($row['created_by_name'] ?? '—')) ?></dd>
                <dt>Disetujui</dt><dd><?= in_array($status, ['approved', 'posted'], true) ? 'Sudah disetujui' : '<span class="muted">—</span>' ?></dd>
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
