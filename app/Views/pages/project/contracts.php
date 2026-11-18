<?php
/**
 * Daftar kontrak proyek.
 *
 * @var array $rows
 * @var array $ringkasan
 * @var array $statusList
 * @var bool $canManage
 */
$totalNilai = 0.0;
$totalDitagih = 0.0;
$totalDiakui = 0.0;
foreach ($rows as $row) {
    $totalNilai += (float) $row['nilai_kontrak'];
    $totalDitagih += (float) $row['ditagih'];
    $totalDiakui += (float) $row['diakui'];
}
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Jumlah Kontrak</div>
        <div class="summary-value"><?= count($rows) ?></div>
        <div class="tiny muted"><?= (int) ($ringkasan['kontrak_aktif'] ?? 0) ?> berstatus aktif</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Kontrak</div>
        <div class="summary-value"><?= e(rupiah_short($totalNilai)) ?></div>
        <div class="tiny muted">Seluruh kontrak tercatat</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sudah Ditagih</div>
        <div class="summary-value"><?= e(rupiah_short($totalDitagih)) ?></div>
        <div class="tiny muted">Invoice AR non-void</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pendapatan Diakui</div>
        <div class="summary-value summary-value--up"><?= e(rupiah_short($totalDiakui)) ?></div>
        <div class="tiny muted">Jurnal pengakuan posted</div>
    </div>
</div>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('file', 22) ?></div>
            <h3>Belum ada kontrak</h3>
            <p>Kontrak diperlukan untuk membuat termin penagihan proyek.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('project/contract/create')) ?>"><?= icon('plus', 15) ?> Kontrak Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th>Kontrak</th>
                        <th style="width:140px">Proyek</th>
                        <th class="num" style="width:140px">Nilai</th>
                        <th class="num" style="width:70px">Termin</th>
                        <th class="num" style="width:140px">Ditagih</th>
                        <th class="num" style="width:140px">Diakui</th>
                        <th style="width:90px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a class="mono strong" href="<?= e(url('project/contract/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a></td>
                            <td>
                                <a class="strong small" href="<?= e(url('project/contract/' . (int) $row['id'])) ?>"><?= e((string) $row['judul']) ?></a>
                                <div class="tiny muted"><?= e((string) ($row['partner_nama'] ?? '—')) ?> · <?= e(date('d M Y', strtotime((string) $row['tanggal']))) ?></div>
                            </td>
                            <td class="small">
                                <?php if (!empty($row['project_id'])): ?>
                                    <a href="<?= e(url('project/' . (int) $row['project_id'])) ?>"><?= e((string) ($row['project_nama'] ?? '—')) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="num mono"><?= e(rupiah((float) $row['nilai_kontrak'])) ?></td>
                            <td class="num small"><?= (int) $row['jumlah_termin'] ?></td>
                            <td class="num mono"><?= e(rupiah((float) $row['ditagih'])) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $row['diakui'])) ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $row['status'] === 'aktif' ? 'sukses' : ((string) $row['status'] === 'batal' ? 'bahaya' : 'netral') ?>">
                                    <?= e($statusList[(string) $row['status']] ?? (string) $row['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
