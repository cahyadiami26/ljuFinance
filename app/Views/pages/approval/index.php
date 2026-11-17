<?php
/**
 * Antrean Approval Center.
 *
 * @var array $rows
 * @var array $statistik
 * @var array $statusList
 * @var array $moduleList
 * @var array $filters
 * @var bool $canAct
 */
$slaChip = static function (array $row): string {
    if ((string) $row['status'] !== 'pending') {
        return '';
    }
    if (empty($row['deadline'])) {
        return '<span class="sla-chip">Tanpa SLA</span>';
    }

    $sisa = strtotime((string) $row['deadline']) - time();
    $jam = (int) floor($sisa / 3600);

    if ($sisa < 0) {
        return '<span class="sla-chip sla-chip--danger">' . icon('clock', 11) . ' Terlambat ' . abs($jam) . ' jam</span>';
    }
    if ($jam <= 8) {
        return '<span class="sla-chip sla-chip--warning">' . icon('clock', 11) . ' Sisa ' . $jam . ' jam</span>';
    }

    return '<span class="sla-chip sla-chip--safe">' . icon('clock', 11) . ' Sisa ' . $jam . ' jam</span>';
};
?>
<div class="kpi-grid mb-16">
    <div class="kpi">
        <div class="kpi-icon kpi-icon--warning"><?= icon('clock', 18) ?></div>
        <div>
            <div class="kpi-label">Menunggu Persetujuan</div>
            <div class="kpi-value"><?= (int) $statistik['pending'] ?></div>
            <div class="kpi-meta"><?= (int) $statistik['terlambat'] ?> melewati SLA</div>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi-icon kpi-icon--success"><?= icon('check', 18) ?></div>
        <div>
            <div class="kpi-label">Disetujui Hari Ini</div>
            <div class="kpi-value"><?= (int) $statistik['hari_ini'] ?></div>
            <div class="kpi-meta"><?= (int) $statistik['ditolak'] ?> ditolak hari ini</div>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi-icon kpi-icon--info"><?= icon('clock', 18) ?></div>
        <div>
            <div class="kpi-label">Rata-rata Penyelesaian</div>
            <div class="kpi-value"><?= e(number_format((float) $statistik['rata_jam'], 1, ',', '.')) ?><span style="font-size:12px;color:var(--ink-3)"> jam</span></div>
            <div class="kpi-meta">Dari pengajuan hingga keputusan</div>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi-icon kpi-icon--primary"><?= icon('check-circle', 18) ?></div>
        <div>
            <div class="kpi-label">Total Permintaan</div>
            <div class="kpi-value"><?= (int) $statistik['total'] ?></div>
            <div class="kpi-meta"><?= (int) $statistik['selesai'] ?> sudah selesai</div>
        </div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('approval')) ?>">
    <input type="hidden" name="r" value="approval">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Cari nomor dokumen atau judul…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>Semua status</option>
        <?php foreach ($statusList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="module" data-autosubmit>
        <option value="">Semua modul</option>
        <?php foreach ($moduleList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filters['module'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="check-inline small">
        <input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue']) ? 'checked' : '' ?> data-autosubmit>
        Hanya terlambat
    </label>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= count($rows) ?> permintaan</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('check-circle', 22) ?></div>
            <h3>Tidak ada permintaan pada filter ini</h3>
            <p>Semua pengajuan pada kriteria tersebut sudah diproses atau belum ada pengajuan baru.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:170px">Dokumen</th>
                        <th style="width:130px">Modul</th>
                        <th>Judul</th>
                        <th class="num" style="width:150px">Nilai</th>
                        <th style="width:110px">Level</th>
                        <th style="width:120px">Pemohon</th>
                        <th style="width:150px">SLA</th>
                        <th style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a class="strong mono" href="<?= e(url('approval/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor_dokumen']) ?></a>
                                <div class="tiny muted"><?= e(tanggal_waktu($row['created_at'])) ?></div>
                            </td>
                            <td><span class="badge badge--netral"><?= e((string) ($moduleList[(string) $row['module']] ?? $row['module'])) ?></span></td>
                            <td>
                                <div class="small"><?= e(str_limit((string) $row['judul'], 64)) ?></div>
                                <div class="tiny muted">
                                    <?= e((string) ($row['divisi_nama'] ?? '—')) ?>
                                    <?= !empty($row['bidang_nama']) ? ' · ' . e((string) $row['bidang_nama']) : '' ?>
                                </div>
                            </td>
                            <td class="num strong"><?= e(rupiah((float) $row['amount'])) ?></td>
                            <td class="small"><?= (int) $row['current_level'] ?> / <?= (int) $row['max_level'] ?></td>
                            <td class="small"><?= e((string) ($row['requested_by_name'] ?? '—')) ?></td>
                            <td><?= $slaChip($row) ?: '<span class="muted">—</span>' ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
