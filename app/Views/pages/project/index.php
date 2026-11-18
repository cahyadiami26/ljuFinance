<?php
/**
 * Portofolio proyek.
 *
 * @var array $rows
 * @var string $q
 * @var int $businessUnitId
 * @var array $businessUnits
 * @var array $ringkasan
 * @var array $statusList
 * @var bool $canManage
 */
$kontrakAktif = (float) ($ringkasan['nilai_kontrak'] ?? 0);
$diakui = (float) ($ringkasan['diakui'] ?? 0);
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Proyek Berjalan</div>
        <div class="summary-value"><?= (int) ($ringkasan['proyek_aktif'] ?? 0) ?></div>
        <div class="tiny muted"><?= (int) ($ringkasan['kontrak_aktif'] ?? 0) ?> kontrak aktif</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Kontrak</div>
        <div class="summary-value"><?= e(rupiah_short($kontrakAktif)) ?></div>
        <div class="tiny muted">Kontrak aktif &amp; selesai</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pendapatan Diakui</div>
        <div class="summary-value"><?= e(rupiah_short($diakui)) ?></div>
        <div class="tiny muted">Realisasi terhadap kontrak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Termin Belum Ditagih</div>
        <div class="summary-value"><?= (int) ($ringkasan['termin_belum'] ?? 0) ?></div>
        <div class="tiny muted">Siap dibuatkan invoice AR</div>
    </div>
</div>

<form class="toolbar mb-16" method="get" action="<?= e(url('project')) ?>">
    <input type="hidden" name="r" value="project">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama proyek…">
    </div>
    <select class="filter-select" name="unit" data-autosubmit>
        <option value="">Semua unit usaha</option>
        <?php foreach ($businessUnits as $unit): ?>
            <option value="<?= (int) $unit['id'] ?>" <?= $businessUnitId === (int) $unit['id'] ? 'selected' : '' ?>><?= e((string) $unit['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= count($rows) ?> proyek</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('briefcase', 22) ?></div>
            <h3>Belum ada proyek</h3>
            <p>Buat proyek terlebih dahulu agar kontrak, tagihan, dan biaya dapat dilacak.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('project/create')) ?>"><?= icon('plus', 15) ?> Proyek Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:130px">Kode</th>
                        <th>Proyek</th>
                        <th style="width:120px">Unit</th>
                        <th style="width:100px">Status</th>
                        <th class="num" style="width:140px">Nilai Kontrak</th>
                        <th class="num" style="width:140px">Pendapatan</th>
                        <th style="width:150px">Progres</th>
                        <th class="num" style="width:130px">Laba</th>
                        <th class="num" style="width:90px">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $progress = (float) ($row['progress'] ?? 0);
                        $laba = (float) ($row['laba'] ?? 0);
                        $margin = (float) ($row['margin'] ?? 0);
                        ?>
                        <tr>
                            <td><a class="mono strong" href="<?= e(url('project/' . (int) $row['id'])) ?>"><?= e((string) $row['kode']) ?></a></td>
                            <td>
                                <a class="strong" href="<?= e(url('project/' . (int) $row['id'])) ?>"><?= e((string) $row['nama']) ?></a>
                                <div class="tiny muted"><?= e((string) ($row['partner_nama'] ?? '—')) ?> · <?= (int) ($row['jumlah_kontrak'] ?? 0) ?> kontrak</div>
                            </td>
                            <td class="small"><?= e((string) ($row['business_unit_nama'] ?? '—')) ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $row['status'] === 'berjalan' ? 'sukses' : ((string) $row['status'] === 'batal' ? 'bahaya' : 'netral') ?>">
                                    <?= e($statusList[(string) $row['status']] ?? (string) $row['status']) ?>
                                </span>
                            </td>
                            <td class="num mono"><?= e(rupiah((float) $row['nilai_kontrak'])) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $row['pendapatan_diakui'])) ?></td>
                            <td>
                                <div class="progress-track progress-track--success">
                                    <span style="width:<?= max(0, min(100, (int) round($progress))) ?>%"></span>
                                </div>
                                <div class="tiny muted"><?= e(number_format($progress, 1, ',', '.')) ?>% dari kontrak</div>
                            </td>
                            <td class="num mono strong" style="color:<?= $laba >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= e(rupiah($laba)) ?></td>
                            <td class="num"><?= e(number_format($margin, 1, ',', '.')) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
