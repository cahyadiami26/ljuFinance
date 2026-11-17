<?php
/**
 * Daftar tagihan vendor (AP) + aging hutang.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $status
 * @var int $partnerId
 * @var array $partners
 * @var array $ringkasan
 * @var array $aging
 * @var array $buckets
 * @var int $tahun
 * @var bool $canCreate
 */
use Lju\Services\ApService;

$agingTotal = array_sum($aging);
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Tagihan <?= (int) $tahun ?></div>
        <div class="summary-value"><?= (int) ($ringkasan['jumlah'] ?? 0) ?></div>
        <div class="tiny muted">Total dokumen hutang</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Tagihan</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['total'] ?? 0))) ?></div>
        <div class="tiny muted">Termasuk PPN masukan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sisa Hutang</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['outstanding'] ?? 0))) ?></div>
        <div class="tiny muted">Belum dibayar</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Lewat Jatuh Tempo</div>
        <div class="summary-value summary-value--down"><?= e(rupiah((float) ($ringkasan['jatuh_tempo'] ?? 0))) ?></div>
        <div class="tiny muted">Perlu prioritas pembayaran</div>
    </div>
</div>

<div class="card mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Aging Hutang</h2>
            <div class="card-sub">Distribusi sisa hutang berdasarkan umur jatuh tempo · total <?= e(rupiah($agingTotal)) ?></div>
        </div>
        <span class="pill"><?= icon('clock', 13) ?> per hari ini</span>
    </div>
    <div class="card-body">
        <div class="aging-grid aging-grid--5">
            <?php foreach ($buckets as $key => $label): ?>
                <?php $nilai = (float) ($aging[$key] ?? 0); ?>
                <div class="aging-card<?= $key === 'b90' && $nilai > 0 ? ' aging-card--danger' : '' ?>">
                    <div class="aging-top">
                        <span class="aging-label"><?= e($label) ?></span>
                        <span class="aging-dot<?= $key === 'b90' ? ' aging-dot--danger' : ($key === 'b61_90' ? ' aging-dot--warning' : '') ?>"></span>
                    </div>
                    <div class="aging-line"><span class="val"><?= e(rupiah($nilai)) ?></span></div>
                    <div class="aging-bar"><span style="width:<?= $agingTotal > 0 ? (int) round($nilai / $agingTotal * 100) : 0 ?>%"></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('ap')) ?>">
    <input type="hidden" name="r" value="ap">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor tagihan, nomor supplier, vendor…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach (ApService::STATUS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="partner" data-autosubmit>
        <option value="">Semua vendor</option>
        <?php foreach ($partners as $partner): ?>
            <option value="<?= (int) $partner['id'] ?>" <?= $partnerId === (int) $partner['id'] ? 'selected' : '' ?>>
                <?= e($partner['kode'] . ' — ' . $partner['nama']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> tagihan</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('arrow-down', 22) ?></div>
            <h3>Belum ada tagihan vendor</h3>
            <p><?= $q !== '' || $status !== '' || $partnerId > 0 ? 'Tidak ada tagihan yang cocok dengan filter.' : 'Catat tagihan supplier, ajukan persetujuan, lalu bayar dengan jurnal otomatis.' ?></p>
            <?php if ($canCreate): ?>
                <a class="btn btn--primary" href="<?= e(url('ap/create')) ?>"><?= icon('plus', 15) ?> Tagihan Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:160px">Nomor</th>
                        <th style="width:150px">Vendor</th>
                        <th style="width:100px">Tanggal</th>
                        <th style="width:110px">Jatuh Tempo</th>
                        <th class="num" style="width:130px">Total</th>
                        <th class="num" style="width:130px">Sisa</th>
                        <th style="width:110px">Status</th>
                        <th style="width:120px">Jurnal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $efektif = ApService::statusEfektif($row); ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('ap/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted"><?= !empty($row['nomor_supplier']) ? 'Supplier: ' . e((string) $row['nomor_supplier']) : 'Tanpa nomor supplier' ?></div>
                            </td>
                            <td class="small"><?= e((string) ($row['partner_nama'] ?? '—')) ?></td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td class="small<?= $efektif === 'overdue' ? ' amt-neg' : '' ?>"><?= e(tanggal($row['tanggal_jatuh_tempo'], 'd M Y')) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['total'])) ?></td>
                            <td class="num strong"><?= e(rupiah((float) $row['sisa'])) ?></td>
                            <td><?= status_badge($efektif) ?></td>
                            <td class="small">
                                <?php if (!empty($row['journal_id'])): ?>
                                    <a class="mono" style="font-size:11.5px" href="<?= e(url('journal/' . (int) $row['journal_id'])) ?>"><?= e((string) $row['journal_nomor']) ?></a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> tagihan</span>
            <?= $paginator->links('ap', array_filter(['q' => $q, 'status' => $status, 'partner' => $partnerId > 0 ? $partnerId : null])) ?>
        </div>
    <?php endif; ?>
</div>
