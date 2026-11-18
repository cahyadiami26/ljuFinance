<?php
/**
 * Proses penyusutan aset per periode.
 *
 * @var array|null $periode
 * @var array $periodeList
 * @var array|null $rencana
 * @var array $daftar
 * @var int $jumlahDraft
 * @var float $totalPosted
 * @var array $metode
 * @var bool $canManage
 */
$rencanaRows = $rencana['rows'] ?? [];
$rencanaTotal = (float) ($rencana['total'] ?? 0);
$dapatDisusut = array_values(array_filter($rencanaRows, static fn ($r) => (float) $r['penyusutan'] > 0));
$periodeId = $periode !== null ? (int) $periode['id'] : 0;
$terbuka = $periode !== null && (string) $periode['status'] === 'open';
?>
<form class="toolbar mb-16" method="get" action="<?= e(url('asset/depreciation')) ?>">
    <input type="hidden" name="r" value="asset/depreciation">
    <select class="filter-select" name="periode" data-autosubmit>
        <?php foreach ($periodeList as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $periodeId === (int) $item['id'] ? 'selected' : '' ?>>
                <?= e((string) $item['nama']) ?> · <?= e((string) $item['status']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <?php if ($periode !== null): ?>
        <span class="pill"><?= e((string) $periode['kode']) ?></span>
        <span class="badge badge--<?= $terbuka ? 'sukses' : 'netral' ?>"><?= $terbuka ? 'Periode terbuka' : 'Periode ' . e((string) $periode['status']) ?></span>
    <?php endif; ?>
</form>

<?php if ($periode === null): ?>
    <div class="card">
        <div class="empty-state">
            <h3>Periode belum dipilih</h3>
            <p>Pilih periode akuntansi untuk menghitung penyusutan.</p>
        </div>
    </div>
<?php else: ?>
    <?php if (!$terbuka): ?>
        <div class="notice notice--warning mb-16">
            <?= icon('alert', 16) ?>
            <div>Periode <strong><?= e((string) $periode['nama']) ?></strong> sudah <?= e((string) $periode['status']) ?>. Penyusutan hanya dapat diproses pada periode terbuka.</div>
        </div>
    <?php endif; ?>

    <div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <div class="summary-item">
            <div class="summary-label">Aset Dapat Disusutkan</div>
            <div class="summary-value"><?= count($dapatDisusut) ?></div>
            <div class="tiny muted">Dari <?= count($rencanaRows) ?> aset ber-umur ekonomis</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Rencana Penyusutan</div>
            <div class="summary-value"><?= e(rupiah($rencanaTotal)) ?></div>
            <div class="tiny muted">Belum disimpan</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Draft Tersimpan</div>
            <div class="summary-value"><?= (int) $jumlahDraft ?></div>
            <div class="tiny muted">Baris menunggu posting</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Sudah Diposting</div>
            <div class="summary-value summary-value--up"><?= e(rupiah($totalPosted)) ?></div>
            <div class="tiny muted">Pada periode ini</div>
        </div>
    </div>

    <?php if ($rencana !== null): ?>
    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Perhitungan Penyusutan</h2>
                <div class="card-sub">Metode mengikuti kategori aset · penyusutan dimulai bulan berikutnya setelah perolehan</div>
            </div>
            <?php if ($canManage && $terbuka): ?>
                <div class="action-buttons">
                    <form method="post" action="<?= e(url('asset/depreciation')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
                        <button class="btn btn--sm" type="submit"><?= icon('save', 14) ?> Simpan Draft</button>
                    </form>
                    <?php if ($jumlahDraft > 0): ?>
                        <form method="post" action="<?= e(url('asset/depreciation/post')) ?>" data-confirm="Posting semua draft penyusutan periode ini ke jurnal?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
                            <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Posting Jurnal</button>
                        </form>
                        <form method="post" action="<?= e(url('asset/depreciation/clear')) ?>" data-confirm="Hapus semua draft penyusutan periode ini?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
                            <button class="btn btn--sm btn--ghost" type="submit"><?= icon('x', 14) ?> Hapus Draft</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Kode</th>
                        <th>Nama Aset</th>
                        <th style="width:170px">Kategori</th>
                        <th style="width:70px">Bln ke</th>
                        <th class="num" style="width:130px">Harga</th>
                        <th class="num" style="width:140px">Akumulasi</th>
                        <th class="num" style="width:140px">Penyusutan</th>
                        <th style="width:90px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rencanaRows as $baris): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $baris['kode']) ?></td>
                            <td class="small"><?= e((string) $baris['nama']) ?></td>
                            <td class="small"><?= e((string) ($baris['kategori_nama'] ?? '—')) ?></td>
                            <td class="small"><?= (int) $baris['bulan_ke'] ?></td>
                            <td class="num"><?= e(rupiah((float) $baris['harga_perolehan'])) ?></td>
                            <td class="num"><?= e(rupiah((float) $baris['akumulasi_penyusutan'])) ?></td>
                            <td class="num strong">
                                <?= (float) $baris['penyusutan'] > 0 ? e(rupiah((float) $baris['penyusutan'])) : '<span class="muted">—</span>' ?>
                            </td>
                            <td class="small">
                                <?php if ((float) $baris['penyusutan'] > 0): ?>
                                    <span class="badge badge--sukses">Siap</span>
                                <?php else: ?>
                                    <span class="tiny muted" title="<?= e((string) $baris['alasan']) ?>"><?= e(str_limit((string) $baris['alasan'], 22)) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="right strong small">TOTAL RENCANA</td>
                        <td class="num strong"><?= e(rupiah($rencanaTotal)) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php endif; ?>

    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Penyusutan Tersimpan</h2>
                <div class="card-sub"><?= count($daftar) ?> baris pada <?= e((string) $periode['nama']) ?></div>
            </div>
        </div>
        <?php if ($daftar === []): ?>
            <div class="empty-state">
                <div class="icon-wrap"><?= icon('trending-down', 22) ?></div>
                <h3>Belum ada penyusutan tersimpan</h3>
                <p>Klik <strong>Simpan Draft</strong> untuk menyimpan hasil perhitungan periode ini.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:150px">Kode</th>
                            <th>Nama Aset</th>
                            <th class="num" style="width:140px">Penyusutan</th>
                            <th class="num" style="width:150px">Akumulasi</th>
                            <th class="num" style="width:140px">Nilai Buku</th>
                            <th style="width:90px">Status</th>
                            <th style="width:150px">Jurnal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar as $baris): ?>
                            <tr>
                                <td class="mono small"><?= e((string) $baris['asset_kode']) ?></td>
                                <td class="small"><?= e((string) $baris['asset_nama']) ?></td>
                                <td class="num"><?= e(rupiah((float) $baris['penyusutan'])) ?></td>
                                <td class="num"><?= e(rupiah((float) $baris['akumulasi'])) ?></td>
                                <td class="num strong"><?= e(rupiah((float) $baris['nilai_buku'])) ?></td>
                                <td><?= status_badge((string) $baris['status']) ?></td>
                                <td class="small">
                                    <?php if (!empty($baris['journal_id'])): ?>
                                        <a class="mono" style="font-size:11.5px" href="<?= e(url('journal/' . (int) $baris['journal_id'])) ?>"><?= e((string) $baris['journal_nomor']) ?></a>
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
<?php endif; ?>
