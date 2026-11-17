<?php
/**
 * Detail jurnal.
 *
 * @var array $journal
 * @var array $lines
 * @var array $attachments
 * @var array $timeline
 * @var bool $periodeOpen
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canApprove
 * @var bool $canPost
 * @var bool $canReverse
 * @var bool $canDelete
 * @var bool $canAttach
 */
use Lju\Services\JournalService;

$status = (string) $journal['status'];
$tipeLabel = JournalService::TIPE[$status] ?? $status;
$totalDebit = (float) $journal['total_debit'];
$totalKredit = (float) $journal['total_kredit'];
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $journal['nomor']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= e(JournalService::TIPE[(string) $journal['tipe']] ?? (string) $journal['tipe']) ?></span>
                <span class="badge badge--netral"><?= e(JournalService::SUMBER[(string) $journal['sumber']] ?? (string) $journal['sumber']) ?></span>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $journal['deskripsi']) ?></h2>
            <div class="card-sub">
                <?= e(tanggal($journal['tanggal'], 'd M Y')) ?>
                <?php if (!empty($journal['periode_nama'])): ?> · periode <?= e((string) $journal['periode_nama']) ?><?php endif; ?>
                <?php if (!empty($journal['referensi'])): ?> · referensi <span class="code-pill"><?= e((string) $journal['referensi']) ?></span><?php endif; ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('journal/' . (int) $journal['id'] . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/submit')) ?>" data-confirm="Ajukan jurnal ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/approve')) ?>" data-confirm="Setujui jurnal ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canPost): ?>
                <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/post')) ?>" data-confirm="Posting jurnal ini ke buku besar? Tindakan ini tidak dapat dibatalkan.">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--dark" type="submit"><?= icon('check-circle', 14) ?> Posting</button>
                </form>
            <?php endif; ?>

            <?php if (($canApprove && $status === 'submitted') || ($canReverse) || ($canDelete)): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove && $status === 'submitted'): ?>
                            <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Tolak jurnal</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan (wajib)" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak Jurnal</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canReverse): ?>
                            <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/reverse')) ?>" class="stack-8" style="margin-top:12px" data-confirm="Buat jurnal pembalik untuk jurnal ini?">
                                <?= csrf_field() ?>
                                <label class="field-label">Jurnal pembalik</label>
                                <input class="input input--sm" type="date" name="tanggal" value="<?= e(today()) ?>">
                                <input class="input input--sm" type="text" name="alasan" placeholder="Alasan pembalikan" maxlength="200">
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('refresh', 14) ?> Buat Pembalik</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/delete')) ?>" style="margin-top:12px"
                                  data-confirm="Hapus draft jurnal ini secara permanen?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus Draft</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($status === 'rejected' && !empty($journal['rejection_reason'])): ?>
        <div class="card-body" style="padding-top:0">
            <div class="notice notice--warning" style="margin:0">
                <?= icon('alert', 16) ?>
                <div>
                    <strong>Ditolak<?= !empty($journal['rejected_by_name']) ? ' oleh ' . e((string) $journal['rejected_by_name']) : '' ?>.</strong>
                    <?= e((string) $journal['rejection_reason']) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($status === 'reversed' && !empty($journal['reversed_by_id'])): ?>
        <div class="card-body" style="padding-top:0">
            <div class="notice" style="margin:0">
                <?= icon('info', 16) ?>
                <div>Jurnal ini telah dibalik oleh <a href="<?= e(url('journal/' . (int) $journal['reversed_by_id'])) ?>">jurnal pembalik</a>. Nilainya tidak lagi dihitung dalam saldo.</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="grid-main">
    <div class="stack-8" style="display:flex;flex-direction:column;gap:16px">
        <div class="card card--flush">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Baris Jurnal</h2>
                    <div class="card-sub"><?= count($lines) ?> baris · total <?= e(rupiah($totalDebit)) ?></div>
                </div>
                <?php if (abs($totalDebit - $totalKredit) < 0.005): ?>
                    <span class="badge badge--sukses"><?= icon('check', 12) ?> Seimbang</span>
                <?php else: ?>
                    <span class="badge badge--bahaya">Tidak seimbang</span>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:36px" class="center">#</th>
                            <th style="width:110px">Kode</th>
                            <th>Akun / Keterangan</th>
                            <th>Dimensi</th>
                            <th class="num" style="width:140px">Debit</th>
                            <th class="num" style="width:140px">Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td class="center small muted"><?= (int) $line['urutan'] ?></td>
                                <td class="mono small"><?= e((string) $line['coa_kode']) ?></td>
                                <td>
                                    <div class="strong" style="font-size:12.5px"><?= e((string) $line['coa_nama']) ?></div>
                                    <?php if (!empty($line['deskripsi'])): ?>
                                        <div class="tiny muted"><?= e((string) $line['deskripsi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="tiny muted">
                                    <?php
                                    $dimensi = array_filter([
                                        $line['bidang_nama'] ?? null,
                                        $line['divisi_nama'] ?? null,
                                        $line['project_nama'] ?? null,
                                        $line['cost_center_nama'] ?? null,
                                        $line['partner_nama'] ?? null,
                                    ]);
                                    echo $dimensi === [] ? '—' : e(implode(' · ', $dimensi));
                                    ?>
                                </td>
                                <td class="num"><?= (float) $line['debit'] > 0 ? e(rupiah($line['debit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="num"><?= (float) $line['kredit'] > 0 ? e(rupiah($line['kredit'])) : '<span class="muted">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="right strong small">TOTAL</td>
                            <td class="num strong"><?= e(rupiah($totalDebit)) ?></td>
                            <td class="num strong"><?= e(rupiah($totalKredit)) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Bukti Transaksi</h2>
                    <div class="card-sub"><?= count($attachments) ?> lampiran · PDF, gambar, atau spreadsheet.</div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($attachments === []): ?>
                    <div class="tiny muted" style="margin-bottom:12px">Belum ada lampiran.</div>
                <?php else: ?>
                    <div class="attach-list">
                        <?php foreach ($attachments as $file): ?>
                            <div class="attach-item">
                                <span class="attach-icon"><?= icon('file', 16) ?></span>
                                <div class="attach-meta">
                                    <a href="<?= e(url('journal/' . (int) $journal['id'] . '/attachment/' . (int) $file['id'])) ?>" target="_blank" rel="noopener">
                                        <?= e((string) $file['file_name']) ?>
                                    </a>
                                    <span class="tiny muted"><?= e(number_format(((int) $file['file_size']) / 1024, 0, ',', '.')) ?> KB · <?= e(tanggal_waktu($file['created_at'], 'd M Y H:i')) ?></span>
                                </div>
                                <?php if ($canAttach): ?>
                                    <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/attachment/' . (int) $file['id'] . '/delete')) ?>"
                                          data-confirm="Hapus lampiran ini?">
                                        <?= csrf_field() ?>
                                        <button class="icon-btn" type="submit" title="Hapus"><?= icon('x', 13) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canAttach): ?>
                    <form method="post" action="<?= e(url('journal/' . (int) $journal['id'] . '/attachment')) ?>" enctype="multipart/form-data" class="row mt-12" style="gap:8px">
                        <?= csrf_field() ?>
                        <input class="input" type="file" name="file" required style="flex:1;padding:7px 10px;font-size:12px">
                        <button class="btn btn--sm" type="submit"><?= icon('download', 14) ?> Unggah</button>
                    </form>
                    <div class="tiny muted mt-8">
                        Maksimal <?= (int) round(((int) config('upload.max_size', 5242880)) / 1048576) ?> MB ·
                        format: <?= e(implode(', ', (array) config('upload.allowed_ext', []))) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack-8" style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Informasi</h2>
            </div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Nomor</dt><dd class="mono"><?= e((string) $journal['nomor']) ?></dd>
                    <dt>Tanggal</dt><dd><?= e(tanggal($journal['tanggal'], 'd M Y')) ?></dd>
                    <dt>Periode</dt><dd><?= e((string) ($journal['periode_nama'] ?? '—')) ?></dd>
                    <dt>Status</dt><dd><?= status_badge($status) ?></dd>
                    <dt>Sumber</dt><dd><?= e(JournalService::SUMBER[(string) $journal['sumber']] ?? (string) $journal['sumber']) ?></dd>
                    <dt>Dibuat</dt><dd><?= e((string) ($journal['created_by_name'] ?? '—')) ?><br><span class="tiny muted"><?= e(tanggal_waktu($journal['created_at'])) ?></span></dd>
                    <?php if (!empty($journal['approved_at'])): ?>
                        <dt>Disetujui</dt><dd><?= e((string) ($journal['approved_by_name'] ?? '—')) ?><br><span class="tiny muted"><?= e(tanggal_waktu($journal['approved_at'])) ?></span></dd>
                    <?php endif; ?>
                    <?php if (!empty($journal['posted_at'])): ?>
                        <dt>Diposting</dt><dd><?= e((string) ($journal['posted_by_name'] ?? '—')) ?><br><span class="tiny muted"><?= e(tanggal_waktu($journal['posted_at'])) ?></span></dd>
                    <?php endif; ?>
                    <?php if (!empty($journal['reversal_of_id'])): ?>
                        <dt>Membalik</dt><dd><a href="<?= e(url('journal/' . (int) $journal['reversal_of_id'])) ?>">Jurnal asal</a></dd>
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
                                    <?= e(tanggal_waktu($event['at'])) ?>
                                    <?php if (!empty($event['by'])): ?> · <?= e((string) $event['by']) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
