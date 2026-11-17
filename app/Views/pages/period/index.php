<?php
/**
 * Periode akuntansi.
 *
 * @var array $periods
 * @var array|null $fiscalYear
 * @var array $years
 * @var int $tahun
 * @var array $journalCounts
 * @var array $statusCount
 * @var array $current
 * @var bool $canManage
 */
?>
<div class="toolbar">
    <form method="get" action="<?= e(url('master/period')) ?>" class="row" style="gap:8px">
        <input type="hidden" name="r" value="master/period">
        <label class="small muted">Tahun buku</label>
        <select class="filter-select" name="tahun" onchange="this.form.submit()">
            <?php foreach ($years as $year): ?>
                <option value="<?= (int) $year ?>" <?= (int) $year === (int) $tahun ? 'selected' : '' ?>><?= (int) $year ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <span class="spacer"></span>
    <span class="pill pill--success"><?= (int) ($statusCount['open'] ?? 0) ?> terbuka</span>
    <span class="pill"><?= (int) ($statusCount['closed'] ?? 0) ?> ditutup</span>
    <span class="pill pill--danger"><?= (int) ($statusCount['locked'] ?? 0) ?> terkunci</span>
</div>

<div class="summary-strip" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Tahun Buku</div>
        <div class="summary-value"><?= (int) $tahun ?></div>
        <div class="tiny muted">
            <?= e(tanggal($fiscalYear['tanggal_mulai'] ?? null)) ?> – <?= e(tanggal($fiscalYear['tanggal_selesai'] ?? null)) ?>
        </div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Status Tahun Buku</div>
        <div class="summary-value"><?= status_badge((string) ($fiscalYear['status'] ?? 'open')) ?></div>
        <div class="tiny muted">Periode aktif: <?= e(periode_label($current)) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Periode Aktif</div>
        <div class="summary-value"><?= e((string) ($current['kode'] ?? '-')) ?></div>
        <div class="tiny muted">Bulan berjalan sistem</div>
    </div>
</div>

<div class="notice notice--info mb-16">
    <?= icon('info', 16) ?>
    <div>
        <strong>Tutup buku otomatis ada pada Tahap 2.</strong>
        Saat ini status periode dapat diubah manual oleh Kepala Bidang Keuangan untuk pengujian alur.
        Proses tutup buku akan membuat jurnal penutup, memindahkan saldo awal, dan mengunci periode secara permanen.
    </div>
</div>

<div class="card card--flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Tanggal Mulai</th>
                    <th>Tanggal Selesai</th>
                    <th class="num">Jurnal</th>
                    <th>Status</th>
                    <th>Ditutup Oleh</th>
                    <?php if ($canManage): ?><th class="right">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods as $period): ?>
                    <?php $isCurrent = (int) ($current['id'] ?? 0) === (int) $period['id']; ?>
                    <tr>
                        <td>
                            <div class="strong"><?= e((string) $period['nama']) ?></div>
                            <div class="tiny muted"><?= e((string) $period['kode']) ?><?= $isCurrent ? ' · periode berjalan' : '' ?></div>
                        </td>
                        <td><?= e(tanggal($period['tanggal_mulai'])) ?></td>
                        <td><?= e(tanggal($period['tanggal_selesai'])) ?></td>
                        <td class="num"><?= (int) ($journalCounts[(int) $period['id']] ?? 0) ?></td>
                        <td><?= status_badge((string) $period['status']) ?></td>
                        <td class="small muted"><?= $period['closed_at'] ? e(tanggal_waktu($period['closed_at'])) : '—' ?></td>
                        <?php if ($canManage): ?>
                            <td>
                                <form method="post" action="<?= e(url('master/period/' . (int) $period['id'] . '/status')) ?>"
                                      class="row" style="gap:6px;justify-content:flex-end"
                                      data-confirm="Ubah status periode <?= e((string) $period['kode']) ?>?">
                                    <?= csrf_field() ?>
                                    <select class="filter-select" name="status" style="height:32px;min-width:110px;font-size:12px">
                                        <?php foreach (['open' => 'Terbuka', 'closed' => 'Ditutup', 'locked' => 'Terkunci'] as $value => $label): ?>
                                            <option value="<?= e($value) ?>" <?= (string) $period['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn--sm" type="submit"><?= icon('save', 14) ?></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
