<?php
/**
 * Daftar aset tetap.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var int $kategori
 * @var string $status
 * @var array $categories
 * @var array $ringkasan
 * @var array $perKategori
 * @var array $statusList
 * @var array $metode
 * @var bool $canManage
 */
use Lju\Services\AssetService;

$totalKategori = array_sum(array_map(static fn ($r) => (float) $r['harga'], $perKategori));
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Jumlah Aset</div>
        <div class="summary-value"><?= (int) $ringkasan['jumlah'] ?></div>
        <div class="tiny muted"><?= (int) $ringkasan['aktif'] ?> aktif · <?= (int) $ringkasan['draft'] ?> draft</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Harga Perolehan</div>
        <div class="summary-value"><?= e(rupiah((float) $ringkasan['harga'])) ?></div>
        <div class="tiny muted">Akumulasi seluruh aset</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Akumulasi Penyusutan</div>
        <div class="summary-value"><?= e(rupiah((float) $ringkasan['akumulasi'])) ?></div>
        <div class="tiny muted">Sampai periode terakhir diposting</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Buku</div>
        <div class="summary-value summary-value--up"><?= e(rupiah((float) $ringkasan['nilai_buku'])) ?></div>
        <div class="tiny muted">Harga perolehan − akumulasi</div>
    </div>
</div>

<div class="card mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Nilai Buku per Kategori</h2>
            <div class="card-sub">Distribusi harga perolehan dan nilai buku aset aktif</div>
        </div>
        <span class="pill"><?= count($perKategori) ?> kategori</span>
    </div>
    <div class="card-body">
        <?php if ($perKategori === []): ?>
            <p class="muted small">Belum ada kategori aset.</p>
        <?php else: ?>
            <?php foreach ($perKategori as $item): ?>
                <?php
                $harga = (float) $item['harga'];
                $lebar = $totalKategori > 0 ? (int) round($harga / $totalKategori * 100) : 0;
                ?>
                <div style="margin-bottom:14px">
                    <div class="row" style="justify-content:space-between">
                        <span class="small strong"><?= e((string) $item['nama']) ?> <span class="tiny muted">(<?= (int) $item['jumlah'] ?> aset)</span></span>
                        <span class="small"><?= e(rupiah_short($harga)) ?> · NB <?= e(rupiah_short((float) $item['nilai_buku'])) ?></span>
                    </div>
                    <div class="progress-track" style="margin-top:6px">
                        <span style="width:<?= $lebar ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('asset')) ?>">
    <input type="hidden" name="r" value="asset">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama, lokasi, atau no. invoice…">
    </div>
    <select class="filter-select" name="kategori" data-autosubmit>
        <option value="">Semua kategori</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= $kategori === (int) $cat['id'] ? 'selected' : '' ?>><?= e((string) $cat['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach ($statusList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> aset</span>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('asset', 22) ?></div>
            <h3>Belum ada aset tetap</h3>
            <p>Tambahkan kartu inventaris agar penyusutan dan nilai buku tercatat otomatis.</p>
            <?php if ($canManage): ?>
                <a class="btn btn--primary" href="<?= e(url('asset/create')) ?>"><?= icon('plus', 15) ?> Aset Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Kode Inventaris</th>
                        <th>Nama Aset</th>
                        <th style="width:140px">Kategori</th>
                        <th style="width:105px">Perolehan</th>
                        <th class="num" style="width:135px">Harga</th>
                        <th class="num" style="width:135px">Akumulasi</th>
                        <th class="num" style="width:135px">Nilai Buku</th>
                        <th style="width:90px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $row['kode']) ?></td>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('asset/' . (int) $row['id'])) ?>"><?= e((string) $row['nama']) ?></a>
                                <div class="tiny muted">
                                    <?= $row['lokasi'] !== null && $row['lokasi'] !== '' ? e((string) $row['lokasi']) : 'Lokasi belum diisi' ?>
                                    <?= !empty($row['penanggung_jawab_nama']) ? ' · ' . e((string) $row['penanggung_jawab_nama']) : '' ?>
                                </div>
                            </td>
                            <td class="small">
                                <?= e((string) ($row['kategori_nama'] ?? '—')) ?>
                                <div class="tiny muted"><?= e((string) ($metode[(string) ($row['metode'] ?? 'garis_lurus')] ?? 'Garis Lurus')) ?></div>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal_perolehan'], 'd M Y')) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['harga_perolehan'])) ?></td>
                            <td class="num"><?= e(rupiah((float) $row['akumulasi_penyusutan'])) ?></td>
                            <td class="num strong"><?= e(rupiah((float) $row['nilai_buku'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> aset</span>
            <?= $paginator->links('asset', array_filter(['q' => $q, 'kategori' => $kategori > 0 ? $kategori : null, 'status' => $status])) ?>
        </div>
    <?php endif; ?>
</div>
