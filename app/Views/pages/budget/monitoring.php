<?php
/**
 * Monitoring anggaran: pagu vs realisasi vs komitmen.
 *
 * @var int $tahun
 * @var int $sampaiBulan
 * @var array|null $budget
 * @var array $monitoring
 * @var array $divisi
 * @var array $costCenter
 * @var array $profitCenter
 * @var array $bulanList
 * @var array $tahunList
 * @var float $alert
 * @var bool $hardStop
 */
$totals = $monitoring['totals'] ?? [];
$rows = $monitoring['rows'] ?? [];
$serapan = (float) ($totals['persen_beban'] ?? 0);
$sisaBeban = (float) ($totals['sisa_beban'] ?? 0);
?>
<div class="notice notice--info mb-16">
    <?= icon('info', 16) ?>
    <div>
        Ambang peringatan <strong><?= e(number_format($alert, 0, ',', '.')) ?>%</strong> serapan.
        <?= $hardStop ? 'Pemblokiran anggaran (hard stop) <strong>aktif</strong>.' : 'Hard stop nonaktif — transaksi tetap dapat diposting meski pagu terlampaui.' ?>
        <span class="tiny muted">Ubah pada Pengaturan &rsaquo; Anggaran.</span>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('budget/monitoring')) ?>">
    <input type="hidden" name="r" value="budget/monitoring">
    <select class="filter-select" name="tahun" data-autosubmit>
        <?php foreach ($tahunList as $opsi): ?>
            <option value="<?= (int) $opsi ?>" <?= (int) $tahun === (int) $opsi ? 'selected' : '' ?>>Tahun <?= (int) $opsi ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="bulan" data-autosubmit>
        <?php foreach ($bulanList as $nomor => $nama): ?>
            <option value="<?= (int) $nomor ?>" <?= (int) $sampaiBulan === (int) $nomor ? 'selected' : '' ?>>s.d. <?= e($nama) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <?php if ($budget !== null): ?>
        <a class="pill" href="<?= e(url('budget/' . (int) $budget['id'])) ?>"><?= e((string) $budget['nomor']) ?></a>
    <?php endif; ?>
</form>

<?php if ($budget === null): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('chart', 22) ?></div>
            <h3>Belum ada RKAP aktif tahun <?= (int) $tahun ?></h3>
            <p>Monitoring membandingkan realisasi jurnal dengan RKAP berstatus aktif. Aktifkan satu versi RKAP terlebih dahulu.</p>
            <a class="btn btn--primary" href="<?= e(url('budget')) ?>"><?= icon('arrow-right', 15) ?> Buka RKAP</a>
        </div>
    </div>
<?php else: ?>
    <div class="summary-card mb-16">
        <div class="summary-card-main">
            <div class="summary-card-label">Serapan Beban s.d. <?= e($bulanList[$sampaiBulan] ?? '') ?> <?= (int) $tahun ?></div>
            <div class="summary-card-value"><?= e(number_format($serapan, 1, ',', '.')) ?><span class="unit">%</span></div>
            <div class="summary-card-meta">
                Pagu beban <?= e(rupiah((float) ($totals['pagu_beban'] ?? 0))) ?>
                · realisasi <?= e(rupiah((float) ($totals['realisasi_beban'] ?? 0))) ?>
            </div>
            <div class="progress-track progress-track--lg" style="margin-top:12px">
                <span style="width:<?= (int) min(100, max(0, $serapan)) ?>%;background:<?= $serapan >= 100 ? 'var(--danger)' : ($serapan >= $alert ? 'var(--warning)' : 'var(--primary)') ?>"></span>
            </div>
        </div>
        <div class="summary-card-side">
            <div class="mini-kpi">
                <div class="mini-kpi-label">Sisa Pagu Beban</div>
                <div class="mini-kpi-value <?= $sisaBeban < 0 ? 'amt-neg' : '' ?>"><?= e(rupiah($sisaBeban)) ?></div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-label">Komitmen Terikat</div>
                <div class="mini-kpi-value"><?= e(rupiah((float) ($totals['komitmen_beban'] ?? 0))) ?></div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-label">Baris Melebihi Pagu</div>
                <div class="mini-kpi-value"><?= (int) ($totals['jumlah_over'] ?? 0) ?></div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-label">Baris Waspada</div>
                <div class="mini-kpi-value"><?= (int) ($totals['jumlah_waspada'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <div class="grid-2 mb-16">
        <div class="card card--flush">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Realisasi per Divisi</h2>
                    <div class="card-sub">Akumulasi beban s.d. bulan terpilih</div>
                </div>
            </div>
            <?php if ($divisi === []): ?>
                <div class="empty-state"><p>Belum ada data divisi pada RKAP aktif.</p></div>
            <?php else: ?>
                <div class="card-body">
                    <?php foreach ($divisi as $item): ?>
                        <?php $persen = (float) $item['persen']; ?>
                        <div style="margin-bottom:14px">
                            <div class="row" style="justify-content:space-between">
                                <span class="small strong"><?= e((string) $item['nama']) ?></span>
                                <span class="small <?= $persen >= 100 ? 'amt-neg' : '' ?>"><?= e(rupiah_short((float) $item['realisasi'])) ?> / <?= e(rupiah_short((float) $item['pagu'])) ?></span>
                            </div>
                            <div class="progress-track" style="margin-top:6px">
                                <span style="width:<?= (int) min(100, max(0, $persen)) ?>%;background:<?= $persen >= 100 ? 'var(--danger)' : ($persen >= $alert ? 'var(--warning)' : 'var(--primary)') ?>"></span>
                            </div>
                            <div class="row" style="justify-content:space-between;margin-top:4px">
                                <span class="tiny muted"><?= e(number_format($persen, 1, ',', '.')) ?>% serapan</span>
                                <span class="tiny muted">Sisa <?= e(rupiah_short((float) $item['sisa'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Ringkasan Pagu</h2>
            </div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Pagu Pendapatan</dt><dd><?= e(rupiah((float) ($totals['pagu_pendapatan'] ?? 0))) ?></dd>
                    <dt>Realisasi Pendapatan</dt><dd><?= e(rupiah((float) ($totals['realisasi_pendapatan'] ?? 0))) ?></dd>
                    <dt>Pagu Beban</dt><dd><?= e(rupiah((float) ($totals['pagu_beban'] ?? 0))) ?></dd>
                    <dt>Realisasi Beban</dt><dd><?= e(rupiah((float) ($totals['realisasi_beban'] ?? 0))) ?></dd>
                    <dt>Komitmen Beban</dt><dd><?= e(rupiah((float) ($totals['komitmen_beban'] ?? 0))) ?></dd>
                    <dt>Sisa Pagu Beban</dt><dd class="<?= $sisaBeban < 0 ? 'amt-neg' : '' ?>"><?= e(rupiah($sisaBeban)) ?></dd>
                    <dt>Beban per Pagu</dt><dd><?= e(number_format((float) ($totals['persen_beban'] ?? 0), 1, ',', '.')) ?>%</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="grid-2 mb-16">
        <div class="card card--flush">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Analisis Cost Center</h2>
                    <div class="card-sub">Pagu beban vs realisasi per pusat biaya</div>
                </div>
            </div>
            <?php if ($costCenter === []): ?>
                <div class="empty-state"><p>Belum ada pagu/realisasi per cost center.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Cost Center</th>
                                <th style="width:80px">Tipe</th>
                                <th class="num" style="width:120px">Pagu</th>
                                <th class="num" style="width:120px">Realisasi</th>
                                <th class="num" style="width:80px">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($costCenter as $cc): ?>
                                <tr>
                                    <td>
                                        <div class="small strong"><?= e((string) $cc['nama']) ?></div>
                                        <div class="tiny muted mono"><?= e((string) $cc['kode']) ?></div>
                                    </td>
                                    <td><span class="badge badge--<?= $cc['tipe'] === 'profit' ? 'sukses' : ($cc['tipe'] === 'investasi' ? 'info' : 'netral') ?>"><?= e(ucfirst((string) $cc['tipe'])) ?></span></td>
                                    <td class="num"><?= e(rupiah_short((float) $cc['pagu'])) ?></td>
                                    <td class="num strong"><?= e(rupiah_short((float) $cc['realisasi'])) ?></td>
                                    <td class="num <?= (float) $cc['persen'] >= 100 ? 'amt-neg' : '' ?>"><?= e(number_format((float) $cc['persen'], 0, ',', '.')) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card card--flush">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Analisis Profit Center</h2>
                    <div class="card-sub">Pendapatan vs beban per unit usaha (margin)</div>
                </div>
            </div>
            <?php if ($profitCenter === []): ?>
                <div class="empty-state"><p>Belum ada pagu/realisasi per unit usaha.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Unit Usaha</th>
                                <th class="num" style="width:110px">Pendapatan</th>
                                <th class="num" style="width:110px">Beban</th>
                                <th class="num" style="width:110px">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profitCenter as $pc): ?>
                                <tr>
                                    <td>
                                        <div class="small strong"><?= e((string) $pc['nama']) ?></div>
                                        <div class="tiny muted">Pagu margin <?= e(rupiah_short((float) $pc['pagu_margin'])) ?></div>
                                    </td>
                                    <td class="num"><?= e(rupiah_short((float) $pc['real_pendapatan'])) ?></td>
                                    <td class="num"><?= e(rupiah_short((float) $pc['real_beban'])) ?></td>
                                    <td class="num strong <?= (float) $pc['real_margin'] < 0 ? 'amt-neg' : '' ?>"><?= e(rupiah_short((float) $pc['real_margin'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Realisasi per Akun &amp; Dimensi</h2>
                <div class="card-sub">Warna menunjukkan status: aman, waspada (&ge; <?= e(number_format($alert, 0, ',', '.')) ?>%), atau melebihi pagu.</div>
            </div>
            <span class="pill"><?= count($rows) ?> baris</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:220px">Akun</th>
                        <th style="width:100px">Jenis</th>
                        <th style="min-width:140px">Dimensi</th>
                        <th class="num" style="width:140px">Pagu</th>
                        <th class="num" style="width:140px">Realisasi</th>
                        <th class="num" style="width:130px">Komitmen</th>
                        <th class="num" style="width:130px">Sisa</th>
                        <th style="width:150px">Serapan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $line): ?>
                        <tr>
                            <td class="small"><span class="mono"><?= e($line['coa_kode']) ?></span> · <?= e($line['coa_nama']) ?></td>
                            <td><span class="badge badge--<?= $line['jenis'] === 'pendapatan' ? 'sukses' : 'netral' ?>"><?= e(ucfirst((string) $line['jenis'])) ?></span></td>
                            <td class="small"><?= $line['dimensi'] !== '' ? e($line['dimensi']) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= e(rupiah($line['pagu'])) ?></td>
                            <td class="num strong"><?= e(rupiah($line['realisasi'])) ?></td>
                            <td class="num"><?= $line['komitmen'] > 0 ? e(rupiah($line['komitmen'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num<?= $line['sisa'] < 0 ? ' amt-neg' : '' ?>"><?= e(rupiah($line['sisa'])) ?></td>
                            <td>
                                <div class="row" style="justify-content:space-between">
                                    <span class="small"><?= e(number_format((float) $line['persen'], 1, ',', '.')) ?>%</span>
                                    <?= status_badge((string) $line['status']) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
