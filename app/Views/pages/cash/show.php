<?php
/**
 * Detail transaksi kas & bank.
 *
 * @var array $row
 * @var array $timeline
 * @var bool $periodeOpen
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canApprove
 * @var bool $canPost
 * @var bool $canDelete
 */
use Lju\Services\CashService;

$status = (string) $row['status'];
$tipe = (string) $row['tipe'];
$jumlah = (float) $row['jumlah'];
$id = (int) $row['id'];
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= e(CashService::TIPE[$tipe] ?? $tipe) ?></span>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['deskripsi']) ?></h2>
            <div class="card-sub">
                <?= e(tanggal($row['tanggal'], 'd M Y')) ?>
                <?php if (!empty($row['periode_nama'])): ?> · periode <?= e((string) $row['periode_nama']) ?><?php endif; ?>
                <?php if (!empty($row['referensi'])): ?> · referensi <span class="code-pill"><?= e((string) $row['referensi']) ?></span><?php endif; ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('cash/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('cash/' . $id . '/submit')) ?>" data-confirm="Ajukan transaksi ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('cash/' . $id . '/approve')) ?>" data-confirm="Setujui transaksi ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canPost): ?>
                <form method="post" action="<?= e(url('cash/' . $id . '/post')) ?>" data-confirm="Posting transaksi ini? Jurnal akan dibuat dan saldo rekening diperbarui.">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--dark" type="submit"><?= icon('check-circle', 14) ?> Posting</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove || $canDelete): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove): ?>
                            <form method="post" action="<?= e(url('cash/' . $id . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Tolak transaksi</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('cash/' . $id . '/delete')) ?>" style="margin-top:12px"
                                  data-confirm="Hapus draft transaksi ini secara permanen?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus Draft</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$periodeOpen && $status === 'approved'): ?>
        <div class="card-body" style="padding-top:0">
            <div class="notice notice--warning" style="margin:0">
                <?= icon('alert', 16) ?>
                <div>Periode transaksi sudah ditutup. Posting tidak dapat dilakukan sampai periode dibuka kembali.</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="grid-main">
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Rincian Transaksi</h2>
                    <div class="card-sub"><?= e(CashService::TIPE[$tipe] ?? $tipe) ?> · metode <?= e(ucfirst((string) $row['metode'])) ?></div>
                </div>
                <div class="summary-value <?= $tipe === 'masuk' ? 'summary-value--up' : ($tipe === 'keluar' ? 'summary-value--down' : '') ?>" style="font-size:20px">
                    <?= e(($tipe === 'masuk' ? '+ ' : ($tipe === 'keluar' ? '− ' : '↔ ')) . rupiah($jumlah)) ?>
                </div>
            </div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Rekening</dt>
                    <dd>
                        <?= e((string) ($row['bank_nama'] ?? '—')) ?>
                        <span class="tiny muted">(<?= e((string) ($row['bank_kode'] ?? '—')) ?>)</span>
                    </dd>
                    <?php if (!empty($row['bank_tujuan_nama'])): ?>
                        <dt>Rekening Tujuan</dt>
                        <dd><?= e((string) $row['bank_tujuan_nama']) ?> <span class="tiny muted">(<?= e((string) ($row['bank_tujuan_kode'] ?? '')) ?>)</span></dd>
                    <?php endif; ?>
                    <dt>Akun Lawan</dt>
                    <dd>
                        <?php if (!empty($row['coa_kode'])): ?>
                            <span class="mono"><?= e((string) $row['coa_kode']) ?></span> · <?= e((string) $row['coa_nama']) ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </dd>
                    <?php if (!empty($row['partner_nama'] ?? null)): ?>
                        <dt>Vendor/Customer</dt><dd><?= e((string) $row['partner_nama']) ?></dd>
                    <?php endif; ?>
                    <dt>Proyek</dt><dd><?= e((string) ($row['project_nama'] ?? '—')) ?></dd>
                    <dt>Bidang / Divisi</dt>
                    <dd><?= e((string) ($row['bidang_nama'] ?? '—')) ?> · <?= e((string) ($row['divisi_nama'] ?? '—')) ?></dd>
                    <dt>Cost Center</dt><dd><?= e((string) ($row['cost_center_nama'] ?? '—')) ?></dd>
                    <dt>Referensi</dt><dd><?= e((string) ($row['referensi'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Jurnal Terkait</h2>
                    <div class="card-sub">Jurnal dibuat otomatis saat posting.</div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($row['journal_id'])): ?>
                    <div class="row" style="justify-content:space-between">
                        <div>
                            <div class="strong mono"><?= e((string) ($row['journal_nomor'] ?? '')) ?></div>
                            <div class="tiny muted">Status jurnal: <?= e((string) ($row['journal_status'] ?? '—')) ?></div>
                        </div>
                        <a class="btn btn--sm" href="<?= e(url('journal/' . (int) $row['journal_id'])) ?>"><?= icon('book', 14) ?> Lihat Jurnal</a>
                    </div>
                <?php else: ?>
                    <div class="tiny muted">Belum ada jurnal. Posting transaksi untuk membuat jurnal otomatis.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Informasi</h2>
            </div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Nomor</dt><dd class="mono"><?= e((string) $row['nomor']) ?></dd>
                    <dt>Tanggal</dt><dd><?= e(tanggal($row['tanggal'], 'd M Y')) ?></dd>
                    <dt>Periode</dt><dd><?= e((string) ($row['periode_nama'] ?? '—')) ?></dd>
                    <dt>Status</dt><dd><?= status_badge($status) ?></dd>
                    <dt>Dibuat</dt><dd><?= e((string) ($row['created_by_name'] ?? '—')) ?><br><span class="tiny muted"><?= e(tanggal_waktu($row['created_at'])) ?></span></dd>
                    <?php if (!empty($row['approved_at'])): ?>
                        <dt>Disetujui</dt><dd><?= e((string) ($row['approved_by_name'] ?? '—')) ?><br><span class="tiny muted"><?= e(tanggal_waktu($row['approved_at'])) ?></span></dd>
                    <?php endif; ?>
                    <?php if (!empty($row['posted_at'])): ?>
                        <dt>Diposting</dt><dd><span class="tiny muted"><?= e(tanggal_waktu($row['posted_at'])) ?></span></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Riwayat Proses</h2>
            </div>
            <div class="card-body">
                <?php if ($timeline === []): ?>
                    <div class="tiny muted">Belum ada riwayat.</div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($timeline as $event): ?>
                            <div class="timeline-item">
                                <span class="timeline-dot timeline-dot--<?= e((string) $event['variant']) ?>"><?= icon((string) $event['icon'], 12) ?></span>
                                <div>
                                    <div class="small strong"><?= e((string) $event['label']) ?></div>
                                    <div class="tiny muted">
                                        <?= e(tanggal_waktu($event['at'])) ?>
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
</div>
