<?php
/**
 * Detail permintaan persetujuan + aksi + riwayat.
 *
 * @var array $row
 * @var array $actions
 * @var array $statusList
 * @var array $moduleList
 * @var array $users
 * @var bool $canAct
 */
$status = (string) $row['status'];
$pending = $status === 'pending';
$terlambat = $pending && !empty($row['deadline']) && strtotime((string) $row['deadline']) < time();
$aksiLabel = ['approve' => 'Disetujui', 'reject' => 'Ditolak', 'return' => 'Dikembalikan', 'delegate' => 'Didelegasikan'];
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor_dokumen']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= e((string) ($moduleList[(string) $row['module']] ?? $row['module'])) ?></span>
                <?php if ($terlambat): ?><span class="badge badge--bahaya">Melewati SLA</span><?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['judul']) ?></h2>
            <div class="card-sub">
                Diajukan <?= e(tanggal_waktu($row['created_at'])) ?> oleh <?= e((string) ($row['requested_by_name'] ?? '—')) ?>
            </div>
        </div>
        <div class="action-buttons">
            <a class="btn btn--sm" href="<?= e(url('approval')) ?>"><?= icon('arrow-left', 14) ?> Antrean</a>
            <?php if ($pending && $canAct): ?>
                <form method="post" action="<?= e(url('approval/' . (int) $row['id'] . '/cancel')) ?>" data-confirm="Batalkan permintaan ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" type="submit"><?= icon('x', 14) ?> Batalkan</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="approval-meta">
            <div class="approval-meta-item">
                <div class="approval-meta-label">Nilai Dokumen</div>
                <div class="approval-meta-value"><?= e(rupiah((float) $row['amount'])) ?></div>
            </div>
            <div class="approval-meta-item">
                <div class="approval-meta-label">Level</div>
                <div class="approval-meta-value"><?= (int) $row['current_level'] ?> dari <?= (int) $row['max_level'] ?></div>
            </div>
            <div class="approval-meta-item">
                <div class="approval-meta-label">Batas Waktu (SLA)</div>
                <div class="approval-meta-value"><?= !empty($row['deadline']) ? e(tanggal_waktu($row['deadline'])) : '—' ?></div>
            </div>
            <div class="approval-meta-item">
                <div class="approval-meta-label">Selesai</div>
                <div class="approval-meta-value"><?= !empty($row['completed_at']) ? e(tanggal_waktu($row['completed_at'])) : '—' ?></div>
            </div>
        </div>

        <div class="row wrap mt-16" style="gap:20px">
            <div><span class="tiny muted">Divisi</span><div class="small strong"><?= e((string) ($row['divisi_nama'] ?? '—')) ?></div></div>
            <div><span class="tiny muted">Bidang</span><div class="small strong"><?= e((string) ($row['bidang_nama'] ?? '—')) ?></div></div>
            <div><span class="tiny muted">Proyek</span><div class="small strong"><?= e((string) ($row['project_nama'] ?? '—')) ?></div></div>
            <div><span class="tiny muted">Ref. Modul</span><div class="small strong">#<?= (int) $row['ref_id'] ?></div></div>
        </div>
    </div>
</div>

<div class="grid-main">
    <div>
        <?php if ($pending && $canAct): ?>
            <div class="card mb-16">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">Keputusan Persetujuan</h2>
                        <div class="card-sub">Keputusan tercatat pada riwayat dan diteruskan ke modul asal.</div>
                    </div>
                </div>
                <form method="post" action="<?= e(url('approval/' . (int) $row['id'] . '/act')) ?>">
                    <?= csrf_field() ?>
                    <div class="card-body">
                        <div class="field mb-12">
                            <label class="field-label" for="a-catatan">Catatan</label>
                            <textarea class="textarea" id="a-catatan" name="catatan" rows="3" placeholder="Catatan keputusan (wajib untuk tolak/kembalikan)" maxlength="500"></textarea>
                        </div>
                        <div class="field">
                            <label class="field-label" for="a-delegasi">Delegasikan ke (opsional)</label>
                            <select class="select" id="a-delegasi" name="delegasi_ke">
                                <option value="">— tidak mendelegasikan —</option>
                                <?php foreach ($users as $user): ?>
                                    <?php if ((int) $user['id'] === (int) ($row['requested_by'] ?? 0)) { continue; } ?>
                                    <option value="<?= (int) $user['id'] ?>"><?= e((string) $user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-foot action-buttons" style="justify-content:flex-end">
                        <button class="btn btn--sm" type="submit" name="aksi" value="return"><?= icon('arrow-left', 14) ?> Kembalikan</button>
                        <button class="btn btn--sm btn--danger" type="submit" name="aksi" value="reject"><?= icon('x', 14) ?> Tolak</button>
                        <button class="btn btn--sm btn--primary" type="submit" name="aksi" value="approve"><?= icon('check', 14) ?> Setujui</button>
                    </div>
                </form>
            </div>
        <?php elseif ($pending): ?>
            <div class="notice notice--info mb-16">
                <?= icon('info', 16) ?>
                <div>Anda hanya dapat melihat permintaan ini. Aksi persetujuan memerlukan izin <span class="mono">approval.act</span>.</div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Riwayat Keputusan</h2>
                    <div class="card-sub"><?= count($actions) ?> catatan aksi</div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($actions === []): ?>
                    <div class="empty-state" style="padding:16px 0">
                        <p>Belum ada aksi. Permintaan masih menunggu keputusan level <?= (int) $row['current_level'] ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($actions as $action): ?>
                            <?php
                            $aksi = (string) $action['action'];
                            $dot = $aksi === 'approve' ? 'sukses' : ($aksi === 'reject' ? 'bahaya' : ($aksi === 'return' ? 'warning' : 'info'));
                            ?>
                            <div class="timeline-item">
                                <span class="timeline-dot timeline-dot--<?= $dot ?>"><?= icon($aksi === 'approve' ? 'check' : ($aksi === 'reject' ? 'x' : 'arrow-left'), 12) ?></span>
                                <div style="flex:1">
                                    <div class="row" style="justify-content:space-between">
                                        <div class="small strong">
                                            Level <?= (int) $action['level'] ?> · <?= e($aksiLabel[$aksi] ?? ucfirst($aksi)) ?>
                                        </div>
                                        <div class="tiny muted"><?= e(tanggal_waktu($action['acted_at'])) ?></div>
                                    </div>
                                    <div class="tiny muted">oleh <?= e((string) ($action['approver_nama'] ?? 'Sistem')) ?></div>
                                    <?php if (!empty($action['delegated_nama'])): ?>
                                        <div class="tiny muted">Didelegasikan ke <?= e((string) $action['delegated_nama']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($action['notes'])): ?>
                                        <div class="notice" style="margin:8px 0 0;padding:8px 10px">
                                            <div class="small">“<?= e((string) $action['notes']) ?>”</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Informasi Permintaan</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Modul</dt><dd><?= e((string) ($moduleList[(string) $row['module']] ?? $row['module'])) ?></dd>
                <dt>Nomor Dokumen</dt><dd class="mono"><?= e((string) $row['nomor_dokumen']) ?></dd>
                <dt>Status</dt><dd><?= status_badge($status) ?></dd>
                <dt>Nilai</dt><dd><?= e(rupiah((float) $row['amount'])) ?></dd>
                <dt>Level Saat Ini</dt><dd><?= (int) $row['current_level'] ?> / <?= (int) $row['max_level'] ?></dd>
                <dt>Pemohon</dt><dd><?= e((string) ($row['requested_by_name'] ?? '—')) ?></dd>
                <dt>Diajukan</dt><dd><?= e(tanggal_waktu($row['created_at'])) ?></dd>
                <dt>Batas SLA</dt><dd><?= !empty($row['deadline']) ? e(tanggal_waktu($row['deadline'])) : '—' ?></dd>
                <dt>Level Terakhir</dt><dd><?= (int) ($row['level_terakhir'] ?? 0) ?></dd>
            </dl>
        </div>
    </div>
</div>
