<?php
/**
 * Daftar rekonsiliasi bank.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var int $bankId
 * @var array $banks
 */
?>
<form class="toolbar" method="get" action="<?= e(url('cash/reconciliation')) ?>">
    <input type="hidden" name="r" value="cash/reconciliation">
    <select class="filter-select" name="bank" data-autosubmit>
        <option value="">Semua rekening</option>
        <?php foreach ($banks as $bank): ?>
            <option value="<?= (int) $bank['id'] ?>" <?= $bankId === (int) $bank['id'] ? 'selected' : '' ?>>
                <?= e($bank['kode'] . ' — ' . $bank['nama']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> rekonsiliasi</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('scale', 22) ?></div>
            <h3>Belum ada rekonsiliasi</h3>
            <p>Mulai rekonsiliasi untuk mencocokkan mutasi buku besar dengan rekening koran.</p>
            <?php if (can('cash.manage')): ?>
                <a class="btn btn--primary" href="<?= e(url('cash/reconciliation/create')) ?>"><?= icon('plus', 15) ?> Rekonsiliasi Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:200px">Rekening</th>
                        <th style="width:150px">Periode</th>
                        <th style="width:180px">Rentang Tanggal</th>
                        <th class="num" style="width:150px">Saldo Buku</th>
                        <th class="num" style="width:150px">Saldo Bank</th>
                        <th class="num" style="width:140px">Selisih</th>
                        <th class="center" style="width:90px">Cocok</th>
                        <th style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $selisih = (float) $row['selisih']; ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('cash/reconciliation/' . (int) $row['id'])) ?>">
                                    <?= e((string) $row['bank_nama']) ?>
                                </a>
                                <div class="tiny muted"><?= e((string) ($row['nama_bank'] ?? '')) ?><?= !empty($row['nomor_rekening']) ? ' · ' . e((string) $row['nomor_rekening']) : '' ?></div>
                            </td>
                            <td class="small"><?= e((string) ($row['periode_nama'] ?? '—')) ?></td>
                            <td class="small"><?= e(tanggal($row['tanggal_mulai'], 'd M')) ?> – <?= e(tanggal($row['tanggal_akhir'], 'd M Y')) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['saldo_buku'])) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['saldo_bank'])) ?></td>
                            <td class="num <?= abs($selisih) > 0.005 ? 'strong' : 'muted' ?>"><?= e(rupiah($selisih)) ?></td>
                            <td class="center small"><?= (int) $row['jumlah_cocok'] ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> rekonsiliasi</span>
            <?= $paginator->links('cash/reconciliation', array_filter(['bank' => $bankId ?: ''])) ?>
        </div>
    <?php endif; ?>
</div>
