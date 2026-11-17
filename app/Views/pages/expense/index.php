<?php
/**
 * Daftar pengajuan biaya.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $status
 * @var string $tipe
 * @var array $ringkasan
 * @var int $tahun
 * @var bool $canCreate
 */
use Lju\Services\ExpenseService;
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Pengajuan <?= (int) $tahun ?></div>
        <div class="summary-value"><?= (int) ($ringkasan['jumlah'] ?? 0) ?></div>
        <div class="tiny muted">Total dokumen biaya</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Pengajuan</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['total'] ?? 0))) ?></div>
        <div class="tiny muted">Sudah termasuk PPN dikurangi PPh</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sudah Dibayar</div>
        <div class="summary-value summary-value--up"><?= e(rupiah((float) ($ringkasan['dibayar'] ?? 0))) ?></div>
        <div class="tiny muted">Jurnal pengeluaran terposting</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Menunggu Proses</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['menunggu'] ?? 0))) ?></div>
        <div class="tiny muted">Diajukan atau disetujui</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('expense')) ?>">
    <input type="hidden" name="r" value="expense">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor, judul, keterangan…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach (ExpenseService::STATUS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="tipe" data-autosubmit>
        <option value="">Semua jenis</option>
        <?php foreach (ExpenseService::TIPE as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> pengajuan</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('receipt', 22) ?></div>
            <h3>Belum ada pengajuan biaya</h3>
            <p><?= $q !== '' || $status !== '' || $tipe !== '' ? 'Tidak ada pengajuan yang cocok dengan filter.' : 'Catat biaya operasional lengkap dengan PPN masukan dan potongan PPh.' ?></p>
            <?php if ($canCreate): ?>
                <a class="btn btn--primary" href="<?= e(url('expense/create')) ?>"><?= icon('plus', 15) ?> Pengajuan Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th style="width:100px">Tanggal</th>
                        <th>Judul / Jenis</th>
                        <th style="width:150px">Pemohon</th>
                        <th class="num" style="width:110px">PPN</th>
                        <th class="num" style="width:110px">PPh</th>
                        <th class="num" style="width:150px">Total</th>
                        <th style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('expense/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted"><?= (int) $row['jumlah_item'] ?> rincian</div>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td>
                                <div class="small"><?= e(str_limit((string) $row['judul'], 60)) ?></div>
                                <div class="tiny muted">
                                    <?= e(ExpenseService::TIPE[(string) $row['tipe']] ?? (string) $row['tipe']) ?>
                                    <?php if (!empty($row['bidang_nama'])): ?> · <?= e((string) $row['bidang_nama']) ?><?php endif; ?>
                                </div>
                            </td>
                            <td class="small"><?= e((string) ($row['pemohon_nama'] ?? '—')) ?></td>
                            <td class="num"><?= (float) $row['ppn'] > 0 ? e(rupiah((float) $row['ppn'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= (float) $row['pph'] > 0 ? e(rupiah((float) $row['pph'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num strong"><?= e(rupiah((float) $row['total'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> pengajuan</span>
            <?= $paginator->links('expense', array_filter(['q' => $q, 'status' => $status, 'tipe' => $tipe])) ?>
        </div>
    <?php endif; ?>
</div>
