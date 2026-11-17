<?php
/**
 * Daftar RKAP per tahun.
 *
 * @var array $rows
 * @var int $tahun
 * @var string $status
 * @var string $q
 * @var array $ringkasan
 * @var array $commitment
 * @var array $tahunList
 * @var bool $canManage
 */
use Lju\Services\BudgetService;
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Versi RKAP <?= (int) $tahun ?></div>
        <div class="summary-value"><?= (int) ($ringkasan['jumlah'] ?? 0) ?></div>
        <div class="tiny muted">Seluruh versi & revisi</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pagu Pendapatan</div>
        <div class="summary-value summary-value--up"><?= e(rupiah((float) ($ringkasan['pendapatan'] ?? 0))) ?></div>
        <div class="tiny muted">Akumulasi versi tahun ini</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pagu Beban</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['beban'] ?? 0))) ?></div>
        <div class="tiny muted">Akumulasi versi tahun ini</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Komitmen Terbuka</div>
        <div class="summary-value"><?= e(rupiah((float) ($commitment['terbuka'] ?? 0))) ?></div>
        <div class="tiny muted"><?= (int) ($commitment['jumlah'] ?? 0) ?> PO/kontrak pengadaan</div>
    </div>
</div>

<?php if (!empty($ringkasan['aktif_nomor'])): ?>
    <div class="notice notice--info mb-16">
        <?= icon('check-circle', 16) ?>
        <div>Pagu aktif tahun <?= (int) $tahun ?>: <strong><?= e((string) $ringkasan['aktif_nomor']) ?></strong>. Transaksi beban dibandingkan terhadap versi ini.</div>
    </div>
<?php else: ?>
    <div class="notice notice--warning mb-16">
        <?= icon('alert', 16) ?>
        <div>Belum ada RKAP berstatus <strong>aktif</strong> untuk tahun <?= (int) $tahun ?>. Monitoring realisasi belum memiliki pagu pembanding.</div>
    </div>
<?php endif; ?>

<form class="toolbar" method="get" action="<?= e(url('budget')) ?>">
    <input type="hidden" name="r" value="budget">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor atau judul RKAP…">
    </div>
    <select class="filter-select" name="tahun" data-autosubmit>
        <?php foreach ($tahunList as $opsiTahun): ?>
            <option value="<?= (int) $opsiTahun ?>" <?= (int) $tahun === (int) $opsiTahun ? 'selected' : '' ?>><?= (int) $opsiTahun ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach (BudgetService::STATUS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= count($rows) ?> dokumen</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('chart', 22) ?></div>
            <h3>Belum ada RKAP tahun <?= (int) $tahun ?></h3>
            <p>Susun pagu pendapatan dan beban per akun, divisi, dan proyek untuk tahun anggaran ini.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('budget/create')) ?>"><?= icon('plus', 15) ?> RKAP Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:190px">Nomor / Versi</th>
                        <th>Judul</th>
                        <th class="num" style="width:150px">Pendapatan</th>
                        <th class="num" style="width:150px">Beban</th>
                        <th class="num" style="width:140px">Selisih</th>
                        <th style="width:100px">Status</th>
                        <th style="width:110px">Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $selisih = (float) $row['total_pendapatan'] - (float) $row['total_beban'];
                        ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('budget/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted">
                                    Versi <?= (int) $row['versi'] ?>
                                    <?= !empty($row['parent_nomor']) ? ' · revisi dari ' . e((string) $row['parent_nomor']) : '' ?>
                                </div>
                            </td>
                            <td>
                                <div class="small"><?= e((string) $row['judul']) ?></div>
                                <div class="tiny muted"><?= (int) $row['jumlah_baris'] ?> baris anggaran · tahun <?= (int) $row['tahun'] ?></div>
                            </td>
                            <td class="num"><?= e(rupiah((float) $row['total_pendapatan'])) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['total_beban'])) ?></td>
                            <td class="num strong<?= $selisih < 0 ? ' amt-neg' : '' ?>"><?= e(rupiah($selisih)) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td class="small"><?= e((string) ($row['created_by_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
