<?php
/**
 * Detail aset tetap: nilai, penyusutan, mutasi, dan aksi.
 *
 * @var array $row
 * @var array $riwayat
 * @var array $mutasi
 * @var array $banks
 * @var array $metode
 * @var array $statusList
 * @var array $mutasiList
 * @var bool $canEdit
 * @var bool $canActivate
 * @var bool $canDeactivate
 * @var bool $canMutate
 * @var bool $canSell
 * @var bool $canDispose
 * @var bool $canDelete
 */
$status = (string) $row['status'];
$id = (int) $row['id'];
$harga = (float) $row['harga_perolehan'];
$akumulasi = (float) $row['akumulasi_penyusutan'];
$nilaiBuku = (float) $row['nilai_buku'];
$persenSusut = $harga > 0 ? ($akumulasi / $harga) * 100 : 0;
$sisaBulan = max(0, (int) $row['umur_bulan'] - count(array_filter($riwayat, static fn ($r) => (string) $r['status'] === 'posted')));
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['kode']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info"><?= e((string) ($row['kategori_nama'] ?? 'Tanpa kategori')) ?></span>
                <?php if (!empty($row['journal_nomor'])): ?>
                    <a class="badge badge--info" href="<?= e(url('journal/' . (int) $row['journal_id'])) ?>"><?= icon('book', 11) ?> <?= e((string) $row['journal_nomor']) ?></a>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['nama']) ?></h2>
            <div class="card-sub">
                Perolehan <?= e(tanggal($row['tanggal_perolehan'], 'd M Y')) ?>
                · <?= e((string) ($row['lokasi'] ?? 'Lokasi belum diisi')) ?>
                <?= !empty($row['penanggung_jawab_nama']) ? ' · PJ ' . e((string) $row['penanggung_jawab_nama']) : '' ?>
            </div>
        </div>
        <div class="action-buttons">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('asset/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>
            <?php if ($canDeactivate): ?>
                <form method="post" action="<?= e(url('asset/' . $id . '/deactivate')) ?>" data-confirm="Nonaktifkan aset ini? Histori tetap tersimpan.">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm" type="submit"><?= icon('x', 14) ?> Nonaktifkan</button>
                </form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
                <form method="post" action="<?= e(url('asset/' . $id . '/delete')) ?>" data-confirm="Hapus aset draft ini secara permanen?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Harga Perolehan</div>
        <div class="summary-value"><?= e(rupiah($harga)) ?></div>
        <div class="tiny muted">Residu <?= e(rupiah((float) $row['nilai_residu'])) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Akumulasi Penyusutan</div>
        <div class="summary-value"><?= e(rupiah($akumulasi)) ?></div>
        <div class="tiny muted"><?= e(number_format($persenSusut, 1, ',', '.')) ?>% dari harga perolehan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Buku</div>
        <div class="summary-value summary-value--up"><?= e(rupiah($nilaiBuku)) ?></div>
        <div class="tiny muted">Nilai buku saat ini</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Umur Ekonomis</div>
        <div class="summary-value"><?= (int) $row['umur_bulan'] ?> bln</div>
        <div class="tiny muted">± <?= $sisaBulan ?> bulan tersisa · <?= e((string) ($metode[(string) ($row['metode'] ?? 'garis_lurus')] ?? 'Garis Lurus')) ?></div>
    </div>
</div>

<?php if ($canActivate): ?>
    <div class="notice notice--warning mb-16">
        <?= icon('alert', 16) ?>
        <div>
            <strong>Aset masih draft.</strong> Aktifkan untuk mencatat jurnal perolehan (D akun aset / K bank atau utang usaha).
            Setelah aktif, aset mulai disusutkan pada periode berikutnya.
        </div>
    </div>
<?php endif; ?>

<div class="grid-main">
    <div>
        <div class="card card--flush mb-16">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Riwayat Penyusutan</h2>
                    <div class="card-sub"><?= count($riwayat) ?> baris penyusutan</div>
                </div>
                <a class="btn btn--sm" href="<?= e(url('asset/depreciation')) ?>"><?= icon('trending-down', 14) ?> Proses Penyusutan</a>
            </div>
            <?php if ($riwayat === []): ?>
                <div class="empty-state">
                    <div class="icon-wrap"><?= icon('trending-down', 22) ?></div>
                    <h3>Belum ada penyusutan</h3>
                    <p>Jalankan proses penyusutan pada halaman Penyusutan Aset untuk periode berjalan.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:150px">Periode</th>
                                <th class="num" style="width:140px">Penyusutan</th>
                                <th class="num" style="width:150px">Akumulasi</th>
                                <th class="num" style="width:140px">Nilai Buku</th>
                                <th style="width:90px">Status</th>
                                <th style="width:150px">Jurnal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $dep): ?>
                                <tr>
                                    <td class="small"><?= e((string) ($dep['periode_nama'] ?? tanggal($dep['tanggal'], 'M Y'))) ?></td>
                                    <td class="num"><?= e(rupiah((float) $dep['penyusutan'])) ?></td>
                                    <td class="num"><?= e(rupiah((float) $dep['akumulasi'])) ?></td>
                                    <td class="num strong"><?= e(rupiah((float) $dep['nilai_buku'])) ?></td>
                                    <td><?= status_badge((string) $dep['status']) ?></td>
                                    <td class="small">
                                        <?php if (!empty($dep['journal_id'])): ?>
                                            <a class="mono" style="font-size:11.5px" href="<?= e(url('journal/' . (int) $dep['journal_id'])) ?>"><?= e((string) $dep['journal_nomor']) ?></a>
                                        <?php else: ?>
                                            <span class="muted">Belum diposting</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Riwayat Mutasi</h2>
                    <div class="card-sub">Perolehan, perpindahan lokasi, penjualan, dan penghapusan</div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($mutasi === []): ?>
                    <p class="muted small">Belum ada mutasi.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($mutasi as $item): ?>
                            <?php
                            $tipeMutasi = (string) $item['tipe'];
                            $dot = $tipeMutasi === 'perolehan' ? 'sukses' : ($tipeMutasi === 'penjualan' ? 'warning' : ($tipeMutasi === 'penghapusan' ? 'bahaya' : 'info'));
                            ?>
                            <div class="timeline-item">
                                <span class="timeline-dot timeline-dot--<?= $dot ?>"><?= icon($tipeMutasi === 'perolehan' ? 'plus' : ($tipeMutasi === 'penjualan' ? 'arrow-up' : ($tipeMutasi === 'penghapusan' ? 'x' : 'arrow-right')), 12) ?></span>
                                <div style="flex:1">
                                    <div class="row" style="justify-content:space-between">
                                        <span class="small strong"><?= e((string) ($mutasiList[$tipeMutasi] ?? ucfirst($tipeMutasi))) ?></span>
                                        <span class="tiny muted"><?= e(tanggal($item['tanggal'], 'd M Y')) ?></span>
                                    </div>
                                    <div class="tiny muted"><?= e((string) ($item['keterangan'] ?? '—')) ?></div>
                                    <?php if ((float) $item['nilai'] > 0): ?>
                                        <div class="tiny">Nilai: <?= e(rupiah((float) $item['nilai'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['journal_id'])): ?>
                                        <a class="tiny mono" href="<?= e(url('journal/' . (int) $item['journal_id'])) ?>"><?= e((string) $item['journal_nomor']) ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if ($canActivate): ?>
            <div class="card mb-16">
                <div class="card-head">
                    <h2 class="card-title">Aktifkan Aset</h2>
                </div>
                <form method="post" action="<?= e(url('asset/' . $id . '/activate')) ?>">
                    <?= csrf_field() ?>
                    <div class="card-body">
                        <div class="field mb-12">
                            <label class="field-label" for="act-bank">Rekening Pembayaran</label>
                            <select class="select" id="act-bank" name="bank_account_id">
                                <option value="">— gunakan utang usaha —</option>
                                <?php foreach ($banks as $bank): ?>
                                    <option value="<?= (int) $bank['id'] ?>"><?= e($bank['nama'] . ' · ' . $bank['nomor_rekening']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="tiny muted">Bila kosong, lawan jurnal memakai akun Utang Usaha.</div>
                        </div>
                    </div>
                    <div class="card-foot row" style="justify-content:flex-end">
                        <button class="btn btn--primary" type="submit"><?= icon('check', 15) ?> Aktifkan &amp; Catat Jurnal</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card mb-16">
            <div class="card-head">
                <h2 class="card-title">Informasi Aset</h2>
            </div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Kode</dt><dd class="mono"><?= e((string) $row['kode']) ?></dd>
                    <dt>Kategori</dt><dd><?= e((string) ($row['kategori_nama'] ?? '—')) ?></dd>
                    <dt>Metode</dt><dd><?= e((string) ($metode[(string) ($row['metode'] ?? 'garis_lurus')] ?? 'Garis Lurus')) ?></dd>
                    <dt>Tarif Tahunan</dt><dd><?= e(number_format((float) ($row['tarif_tahunan'] ?? 0), 2, ',', '.')) ?>%</dd>
                    <dt>No. Invoice</dt><dd><?= e((string) ($row['no_invoice'] ?? '—')) ?></dd>
                    <dt>Bidang</dt><dd><?= e((string) ($row['bidang_nama'] ?? '—')) ?></dd>
                    <dt>Divisi</dt><dd><?= e((string) ($row['divisi_nama'] ?? '—')) ?></dd>
                    <dt>Proyek</dt><dd><?= e((string) ($row['project_nama'] ?? '—')) ?></dd>
                    <dt>Keterangan</dt><dd><?= e((string) ($row['keterangan'] ?? '—')) ?></dd>
                    <dt>Dibuat</dt><dd><?= e(tanggal_waktu($row['created_at'] ?? null)) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($canMutate): ?>
            <div class="card mb-16">
                <div class="card-head">
                    <h2 class="card-title">Mutasi Lokasi</h2>
                </div>
                <form method="post" action="<?= e(url('asset/' . $id . '/location')) ?>">
                    <?= csrf_field() ?>
                    <div class="card-body">
                        <div class="field mb-12">
                            <label class="field-label" for="loc-lokasi">Lokasi Tujuan</label>
                            <input class="input" type="text" id="loc-lokasi" name="lokasi" required maxlength="150" placeholder="mis. Gudang Natar">
                        </div>
                        <div class="field mb-12">
                            <label class="field-label" for="loc-tanggal">Tanggal</label>
                            <input class="input" type="date" id="loc-tanggal" name="tanggal" value="<?= e(today()) ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="loc-ket">Keterangan</label>
                            <input class="input" type="text" id="loc-ket" name="keterangan" maxlength="255" placeholder="Opsional">
                        </div>
                    </div>
                    <div class="card-foot row" style="justify-content:flex-end">
                        <button class="btn btn--sm" type="submit"><?= icon('arrow-right', 14) ?> Simpan Mutasi</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($canSell || $canDispose): ?>
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Penjualan / Penghapusan</h2>
                </div>
                <div class="card-body">
                    <?php if ($canSell): ?>
                        <form method="post" action="<?= e(url('asset/' . $id . '/sell')) ?>" class="mb-16" data-confirm="Catat penjualan aset ini? Jurnal penjualan akan dibuat.">
                            <?= csrf_field() ?>
                            <div class="field mb-12">
                                <label class="field-label" for="sell-harga">Harga Jual</label>
                                <input class="input money" type="text" inputmode="numeric" data-money id="sell-harga" name="harga_jual" required placeholder="0">
                                <div class="tiny muted">Nilai buku saat ini <?= e(rupiah($nilaiBuku)) ?> — selisih dicatat sebagai laba/rugi.</div>
                            </div>
                            <div class="field mb-12">
                                <label class="field-label" for="sell-bank">Rekening Penerimaan</label>
                                <select class="select" id="sell-bank" name="bank_account_id" required>
                                    <option value="">— pilih rekening —</option>
                                    <?php foreach ($banks as $bank): ?>
                                        <option value="<?= (int) $bank['id'] ?>"><?= e($bank['nama'] . ' · ' . $bank['nomor_rekening']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field mb-12">
                                <label class="field-label" for="sell-tanggal">Tanggal Jual</label>
                                <input class="input" type="date" id="sell-tanggal" name="tanggal" value="<?= e(today()) ?>">
                            </div>
                            <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Catat Penjualan</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canDispose): ?>
                        <form method="post" action="<?= e(url('asset/' . $id . '/dispose')) ?>" data-confirm="Hapuskan aset ini dari pembukuan? Tindakan ini membuat jurnal penghapusan.">
                            <?= csrf_field() ?>
                            <div class="field mb-12">
                                <label class="field-label" for="disp-alasan">Alasan Penghapusan</label>
                                <textarea class="textarea" id="disp-alasan" name="keterangan" rows="2" required maxlength="255" placeholder="mis. rusak berat, tidak ekonomis"></textarea>
                            </div>
                            <div class="field mb-12">
                                <label class="field-label" for="disp-tanggal">Tanggal</label>
                                <input class="input" type="date" id="disp-tanggal" name="tanggal" value="<?= e(today()) ?>">
                            </div>
                            <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Hapuskan Aset</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
