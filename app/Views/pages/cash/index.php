<?php
/**
 * Daftar transaksi kas & bank.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $status
 * @var string $tipe
 * @var int $bankId
 * @var array $banks
 * @var array $ringkasan
 * @var int $tahun
 * @var bool $canCreate
 */
use Lju\Services\CashService;

$tipeLabel = CashService::TIPE;
$statusLabel = CashService::STATUS;

$saldoTotal = 0.0;
foreach ($banks as $bank) {
    $saldoTotal += (float) $bank['saldo_berjalan'];
}
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Kas Masuk <?= (int) $tahun ?></div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['masuk'] ?? 0))) ?></div>
        <div class="tiny muted">Total transaksi posted</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Kas Keluar <?= (int) $tahun ?></div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['keluar'] ?? 0))) ?></div>
        <div class="tiny muted">Termasuk petty cash</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Transfer</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['transfer'] ?? 0))) ?></div>
        <div class="tiny muted">Antar rekening internal</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Menunggu Proses</div>
        <div class="summary-value"><?= (int) ($ringkasan['menunggu'] ?? 0) ?></div>
        <div class="tiny muted"><?= (int) ($ringkasan['posted'] ?? 0) ?> transaksi sudah posted</div>
    </div>
</div>

<div class="bank-grid mb-16">
    <?php foreach ($banks as $bank): ?>
        <?php $saldo = (float) $bank['saldo_berjalan']; ?>
        <a class="bank-card" href="<?= e(url('cash', ['bank' => (int) $bank['id']])) ?>">
            <div class="bank-card-head">
                <span class="bank-icon"><?= icon((string) $bank['tipe'] === 'petty_cash' ? 'coins' : 'bank', 16) ?></span>
                <div>
                    <div class="bank-name"><?= e((string) $bank['nama']) ?></div>
                    <div class="tiny muted">
                        <?= e((string) ($bank['nama_bank'] ?? ucfirst((string) $bank['tipe']))) ?>
                        <?php if (!empty($bank['nomor_rekening'])): ?> · <?= e((string) $bank['nomor_rekening']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="bank-balance <?= $saldo < 0 ? 'is-negative' : '' ?>"><?= e(rupiah($saldo)) ?></div>
            <div class="tiny muted">COA <?= e((string) ($bank['coa_kode'] ?? '—')) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('cash')) ?>">
    <input type="hidden" name="r" value="cash">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor, deskripsi, referensi…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach ($statusLabel as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="tipe" data-autosubmit>
        <option value="">Semua tipe</option>
        <?php foreach ($tipeLabel as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="bank" data-autosubmit>
        <option value="">Semua rekening</option>
        <?php foreach ($banks as $bank): ?>
            <option value="<?= (int) $bank['id'] ?>" <?= $bankId === (int) $bank['id'] ? 'selected' : '' ?>>
                <?= e((string) $bank['kode'] . ' — ' . $bank['nama']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill">Saldo total <?= e(rupiah_short($saldoTotal)) ?></span>
    <span class="pill"><?= (int) $paginator->total ?> transaksi</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('wallet', 22) ?></div>
            <h3>Belum ada transaksi kas</h3>
            <p><?= $q !== '' || $status !== '' || $tipe !== '' || $bankId > 0 ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Catat penerimaan, pengeluaran, atau transfer antar rekening.' ?></p>
            <?php if ($canCreate): ?>
                <a class="btn btn--primary" href="<?= e(url('cash/create')) ?>"><?= icon('plus', 15) ?> Transaksi Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th style="width:100px">Tanggal</th>
                        <th>Deskripsi</th>
                        <th style="width:190px">Rekening</th>
                        <th class="num" style="width:150px">Jumlah</th>
                        <th style="width:110px">Status</th>
                        <th class="right" style="width:70px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $tipeRow = (string) $row['tipe'];
                        $jumlah = (float) $row['jumlah'];
                        $tanda = $tipeRow === 'masuk' ? '+' : ($tipeRow === 'keluar' ? '−' : '↔');
                        ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('cash/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted"><?= e($tipeLabel[$tipeRow] ?? $tipeRow) ?></div>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td>
                                <div class="small"><?= e(str_limit((string) $row['deskripsi'], 64)) ?></div>
                                <div class="tiny muted">
                                    <?php if (!empty($row['coa_kode'])): ?>
                                        <?= e((string) $row['coa_kode'] . ' · ' . $row['coa_nama']) ?>
                                    <?php elseif (!empty($row['bank_tujuan_nama'])): ?>
                                        Tujuan: <?= e((string) $row['bank_tujuan_nama']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($row['referensi'])): ?> · <?= e((string) $row['referensi']) ?><?php endif; ?>
                                </div>
                            </td>
                            <td class="small">
                                <?= e((string) ($row['bank_nama'] ?? '—')) ?>
                                <?php if (!empty($row['bank_tujuan_nama'])): ?>
                                    <div class="tiny muted">→ <?= e((string) $row['bank_tujuan_nama']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num strong"><?= e($tanda . ' ' . rupiah($jumlah)) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td class="right">
                                <a class="icon-btn" href="<?= e(url('cash/' . (int) $row['id'])) ?>" title="Detail"><?= icon('eye', 14) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> transaksi</span>
            <?= $paginator->links('cash', array_filter(['q' => $q, 'status' => $status, 'tipe' => $tipe, 'bank' => $bankId ?: ''])) ?>
        </div>
    <?php endif; ?>
</div>
