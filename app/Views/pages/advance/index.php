<?php
/**
 * Daftar uang muka kegiatan.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $status
 * @var array $sum
 * @var int $tahun
 * @var bool $canCreate
 */
use Lju\Services\AdvanceService;
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Pengajuan <?= (int) $tahun ?></div>
        <div class="summary-value"><?= (int) ($sum['jumlah'] ?? 0) ?></div>
        <div class="tiny muted">Total dokumen uang muka</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Diajukan</div>
        <div class="summary-value"><?= e(rupiah((float) ($sum['diajukan'] ?? 0))) ?></div>
        <div class="tiny muted">Seluruh pengajuan tahun ini</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sudah Dicairkan</div>
        <div class="summary-value"><?= e(rupiah((float) ($sum['dicairkan'] ?? 0))) ?></div>
        <div class="tiny muted">Dana keluar dari rekening</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Belum Dipertanggungjawabkan</div>
        <div class="summary-value"><?= e(rupiah((float) ($sum['beredar'] ?? 0))) ?></div>
        <div class="tiny muted">Uang muka berjalan</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('advance')) ?>">
    <input type="hidden" name="r" value="advance">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor atau keperluan…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach (AdvanceService::STATUS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> pengajuan</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('coins', 22) ?></div>
            <h3>Belum ada pengajuan uang muka</h3>
            <p><?= $q !== '' || $status !== '' ? 'Tidak ada pengajuan yang cocok dengan filter.' : 'Buat pengajuan dana kegiatan untuk kebutuhan operasional di lapangan.' ?></p>
            <?php if ($canCreate): ?>
                <a class="btn btn--primary" href="<?= e(url('advance/create')) ?>"><?= icon('plus', 15) ?> Pengajuan Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th style="width:100px">Tanggal</th>
                        <th>Keperluan</th>
                        <th style="width:150px">Pemohon</th>
                        <th class="num" style="width:140px">Diajukan</th>
                        <th class="num" style="width:140px">Dicairkan</th>
                        <th class="num" style="width:130px">Sisa</th>
                        <th style="width:130px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('advance/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted"><?= e((string) ($row['periode_nama'] ?? '')) ?></div>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td>
                                <div class="small"><?= e(str_limit((string) $row['keperluan'], 64)) ?></div>
                                <div class="tiny muted"><?= e((string) ($row['bidang_nama'] ?? '—')) ?></div>
                            </td>
                            <td class="small"><?= e((string) ($row['pemohon_nama'] ?? '—')) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['jumlah_diajukan'])) ?></td>
                            <td class="num"><?= (float) $row['jumlah_dicairkan'] > 0 ? e(rupiah((float) $row['jumlah_dicairkan'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= (float) $row['sisa'] != 0.0 ? e(rupiah((float) $row['sisa'])) : '<span class="muted">—</span>' ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> pengajuan</span>
            <?= $paginator->links('advance', array_filter(['q' => $q, 'status' => $status])) ?>
        </div>
    <?php endif; ?>
</div>
