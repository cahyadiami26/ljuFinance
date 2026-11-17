<?php
/**
 * Daftar pengakuan pendapatan kontrak.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $status
 * @var int $contractId
 * @var array $contracts
 * @var array $ringkasan
 * @var array $metode
 * @var int $tahun
 */
use Lju\Services\RevenueService;
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Dokumen <?= (int) $tahun ?></div>
        <div class="summary-value"><?= (int) ($ringkasan['jumlah'] ?? 0) ?></div>
        <div class="tiny muted">Pengakuan pendapatan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Diakui</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['total'] ?? 0))) ?></div>
        <div class="tiny muted">Seluruh dokumen tahun ini</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sudah Diposting</div>
        <div class="summary-value summary-value--up"><?= e(rupiah((float) ($ringkasan['posted'] ?? 0))) ?></div>
        <div class="tiny muted">Tercatat di jurnal</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Dalam Proses</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['proses'] ?? 0))) ?></div>
        <div class="tiny muted">Draft s.d. disetujui</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('revenue')) ?>">
    <input type="hidden" name="r" value="revenue">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor, kontrak, proyek…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach (RevenueService::STATUS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="contract" data-autosubmit>
        <option value="">Semua kontrak</option>
        <?php foreach ($contracts as $contract): ?>
            <option value="<?= (int) $contract['id'] ?>" <?= $contractId === (int) $contract['id'] ? 'selected' : '' ?>>
                <?= e($contract['nomor'] . ' — ' . str_limit((string) $contract['judul'], 40)) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> dokumen</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('chart', 22) ?></div>
            <h3>Belum ada pengakuan pendapatan</h3>
            <p><?= $q !== '' || $status !== '' || $contractId > 0 ? 'Tidak ada dokumen yang cocok dengan filter.' : 'Akui pendapatan kontrak berdasarkan persentase penyelesaian atau termin.' ?></p>
            <?php if (can('ar.manage')): ?>
                <a class="btn btn--primary" href="<?= e(url('revenue/create')) ?>"><?= icon('plus', 15) ?> Pengakuan Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th>Kontrak / Proyek</th>
                        <th style="width:130px">Periode</th>
                        <th style="width:160px">Metode</th>
                        <th class="num" style="width:110px">%</th>
                        <th class="num" style="width:150px">Nilai</th>
                        <th style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $nilaiKontrak = (float) ($row['nilai_kontrak'] ?? 0);
                        $persentase = (float) ($row['persentase'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('revenue/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></div>
                            </td>
                            <td>
                                <div class="small strong"><?= e((string) ($row['contract_nomor'] ?? '—')) ?> · <?= e(str_limit((string) ($row['contract_judul'] ?? $row['project_nama'] ?? ''), 52)) ?></div>
                                <div class="tiny muted"><?= e((string) ($row['partner_nama'] ?? '—')) ?><?= !empty($row['project_nama']) ? ' · ' . e((string) $row['project_nama']) : '' ?></div>
                            </td>
                            <td class="small"><?= e((string) ($row['periode_nama'] ?? '—')) ?></td>
                            <td>
                                <span class="rev-method"><?= icon('chart', 13) ?> <?= e($metode[(string) $row['metode']] ?? (string) $row['metode']) ?></span>
                                <?php if ($nilaiKontrak > 0): ?>
                                    <div class="progress-track" style="margin-top:6px"><span style="width:<?= (int) min(100, $persentase) ?>%"></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= e(number_format($persentase, 2, ',', '.')) ?>%</td>
                            <td class="num strong"><?= e(rupiah((float) $row['nilai'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> dokumen</span>
            <?= $paginator->links('revenue', array_filter(['q' => $q, 'status' => $status, 'contract' => $contractId > 0 ? $contractId : null])) ?>
        </div>
    <?php endif; ?>
</div>
