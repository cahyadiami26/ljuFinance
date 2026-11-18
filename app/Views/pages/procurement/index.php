<?php
/**
 * Daftar purchase order.
 *
 * @var array $rows
 * @var string $q
 * @var string $status
 * @var array $statusList
 * @var array $ringkasan
 * @var bool $canManage
 */
$jumlah = (array) ($ringkasan['status'] ?? []);
$nilai = (array) ($ringkasan['nilai'] ?? []);
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Total PO</div>
        <div class="summary-value"><?= (int) ($ringkasan['total_po'] ?? 0) ?></div>
        <div class="tiny muted">Seluruh status</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai PO</div>
        <div class="summary-value"><?= e(rupiah_short((float) ($ringkasan['nilai_total'] ?? 0))) ?></div>
        <div class="tiny muted">Termasuk PPN</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Menunggu Persetujuan</div>
        <div class="summary-value <?= (int) ($ringkasan['menunggu'] ?? 0) > 0 ? 'summary-value--down' : '' ?>"><?= (int) ($ringkasan['menunggu'] ?? 0) ?></div>
        <div class="tiny muted">Status diajukan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">PO Terbuka</div>
        <div class="summary-value"><?= (int) ($ringkasan['terbuka'] ?? 0) ?></div>
        <div class="tiny muted">Disetujui / diterima sebagian</div>
    </div>
</div>

<form class="toolbar mb-16" method="get" action="<?= e(url('procurement')) ?>">
    <input type="hidden" name="r" value="procurement">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor PO, vendor, atau proyek…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach ($statusList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= count($rows) ?> PO</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('truck', 22) ?></div>
            <h3>Belum ada purchase order</h3>
            <p>PO menjadi komitmen anggaran dan dasar penerimaan barang serta tagihan vendor.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('procurement/create')) ?>"><?= icon('plus', 15) ?> PO Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:140px">Nomor PO</th>
                        <th style="width:105px">Tanggal</th>
                        <th>Vendor</th>
                        <th style="width:140px">Proyek</th>
                        <th class="num" style="width:140px">Total</th>
                        <th style="width:130px">Penerimaan</th>
                        <th style="width:130px">Status</th>
                        <th style="width:100px">Tagihan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $qty = (float) $row['total_qty'];
                        $terima = (float) $row['diterima_qty'];
                        $persen = $qty > 0 ? min(100, $terima / $qty * 100) : 0;
                        ?>
                        <tr>
                            <td><a class="mono strong" href="<?= e(url('procurement/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a></td>
                            <td class="small"><?= e(date('d M Y', strtotime((string) $row['tanggal']))) ?></td>
                            <td class="small"><?= e((string) $row['partner_nama']) ?></td>
                            <td class="small"><?= e((string) ($row['project_nama'] ?? '—')) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $row['total'])) ?></td>
                            <td>
                                <div class="progress-track progress-track--success">
                                    <span style="width:<?= (int) round($persen) ?>%"></span>
                                </div>
                                <div class="tiny muted"><?= e(number_format($persen, 0, ',', '.')) ?>% qty</div>
                            </td>
                            <td>
                                <span class="badge badge--<?= (string) $row['status'] === 'disetujui' || (string) $row['status'] === 'selesai' ? 'sukses' : ((string) $row['status'] === 'batal' ? 'bahaya' : ((string) $row['status'] === 'diajukan' ? 'warning' : 'netral')) ?>">
                                    <?= e($statusList[(string) $row['status']] ?? (string) $row['status']) ?>
                                </span>
                            </td>
                            <td class="small">
                                <?php if (!empty($row['ap_invoice_id'])): ?>
                                    <a class="mono" href="<?= e(url('ap/' . (int) $row['ap_invoice_id'])) ?>">AP</a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($jumlah !== []): ?>
    <div class="card mt-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Sebaran Status</h2>
                <div class="card-sub">Jumlah dan nilai PO per status</div>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($statusList as $value => $label): ?>
                <?php if ((int) ($jumlah[$value] ?? 0) === 0) { continue; } ?>
                <div class="row" style="justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)">
                    <span class="small strong"><?= e($label) ?></span>
                    <span class="small"><?= (int) $jumlah[$value] ?> PO · <?= e(rupiah((float) ($nilai[$value] ?? 0))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
