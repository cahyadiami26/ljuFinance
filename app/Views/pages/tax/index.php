<?php
/**
 * Daftar dokumen pajak.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $tipe
 * @var string $jenis
 * @var int $periodeId
 * @var array $periodeList
 * @var array $ringkasan
 * @var array $tipeList
 * @var array $jenisList
 * @var array $statusList
 * @var bool $canManage
 */
$labelPeriode = 'periode aktif';
foreach ($periodeList as $item) {
    if ((int) $item['id'] === $periodeId) {
        $labelPeriode = (string) $item['nama'];
        break;
    }
}
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">PPN Keluaran</div>
        <div class="summary-value"><?= e(rupiah((float) $ringkasan['keluaran_pajak'])) ?></div>
        <div class="tiny muted">DPP <?= e(rupiah_short((float) $ringkasan['keluaran_dpp'])) ?> · <?= $labelPeriode ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">PPN Masukan</div>
        <div class="summary-value"><?= e(rupiah((float) $ringkasan['masukan_pajak'])) ?></div>
        <div class="tiny muted">DPP <?= e(rupiah_short((float) $ringkasan['masukan_dpp'])) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Kurang Bayar PPN</div>
        <div class="summary-value <?= (float) $ringkasan['kurang_bayar'] > 0 ? 'summary-value--down' : 'summary-value--up' ?>">
            <?= e(rupiah((float) $ringkasan['kurang_bayar'])) ?>
        </div>
        <div class="tiny muted">Keluaran − masukan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">PPh Dipotong</div>
        <div class="summary-value"><?= e(rupiah((float) $ringkasan['potongan'])) ?></div>
        <div class="tiny muted">Bukti potong periode ini</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('tax')) ?>">
    <input type="hidden" name="r" value="tax">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor, NPWP, atau partner…">
    </div>
    <select class="filter-select" name="tipe" data-autosubmit>
        <option value="">Semua tipe</option>
        <?php foreach ($tipeList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="jenis" data-autosubmit>
        <option value="">Semua jenis pajak</option>
        <?php foreach ($jenisList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $jenis === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="periode" data-autosubmit>
        <option value="">Semua periode</option>
        <?php foreach ($periodeList as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $periodeId === (int) $item['id'] ? 'selected' : '' ?>><?= e((string) $item['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> dokumen</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('receipt', 22) ?></div>
            <h3>Belum ada dokumen pajak</h3>
            <p>Catat faktur keluaran/masukan, bukti potong, dan SSP agar rekap SPT dapat dihitung.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('tax/create')) ?>"><?= icon('plus', 15) ?> Dokumen Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:180px">Nomor</th>
                        <th style="width:130px">Tipe</th>
                        <th style="width:150px">Jenis Pajak</th>
                        <th>Partner / Keterangan</th>
                        <th style="width:105px">Tanggal</th>
                        <th class="num" style="width:130px">DPP</th>
                        <th class="num" style="width:120px">Pajak</th>
                        <th style="width:100px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a class="mono strong" style="font-size:11.5px" href="<?= e(url('tax/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <div class="tiny muted"><?= e((string) ($row['periode_nama'] ?? '—')) ?></div>
                            </td>
                            <td><span class="badge badge--netral"><?= e((string) ($tipeList[(string) $row['tipe']] ?? $row['tipe'])) ?></span></td>
                            <td class="small">
                                <?= e((string) $row['tax_kode']) ?>
                                <div class="tiny muted"><?= e((string) $row['tax_nama']) ?></div>
                            </td>
                            <td class="small">
                                <?= e((string) ($row['partner_nama'] ?? '—')) ?>
                                <?php if (!empty($row['npwp'])): ?>
                                    <div class="tiny muted mono"><?= e((string) $row['npwp']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['dpp'])) ?></td>
                            <td class="num strong"><?= e(rupiah((float) $row['nilai_pajak'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> dokumen</span>
            <?= $paginator->links('tax', array_filter(['q' => $q, 'tipe' => $tipe, 'jenis' => $jenis, 'periode' => $periodeId > 0 ? $periodeId : null])) ?>
        </div>
    <?php endif; ?>
</div>
