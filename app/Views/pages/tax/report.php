<?php
/**
 * Rekap pajak bulanan & SPT.
 *
 * @var array|null $periode
 * @var int $periodeId
 * @var array $periodeList
 * @var array $rekap
 * @var array $reports
 * @var array $efaktur
 * @var array $tipeList
 * @var array $jenisList
 * @var array $reportJenis
 * @var bool $canManage
 */
$perTipe = $rekap['per_tipe'] ?? [];
$perJenis = $rekap['per_jenis'] ?? [];
$ppnKeluaran = (float) ($perTipe['faktur_keluaran']['pajak'] ?? 0);
$ppnMasukan = (float) ($perTipe['faktur_masukan']['pajak'] ?? 0);
$kurangBayar = $ppnKeluaran - $ppnMasukan;
$totalPph = 0.0;
foreach (['bukti_potong', 'ssp'] as $tipePotong) {
    $totalPph += (float) ($perTipe[$tipePotong]['pajak'] ?? 0);
}
$sudahDibuat = [];
foreach ($reports as $report) {
    $sudahDibuat[(string) $report['jenis']] = $report;
}
?>
<form class="toolbar mb-16" method="get" action="<?= e(url('tax/report')) ?>">
    <input type="hidden" name="r" value="tax/report">
    <select class="filter-select" name="periode" data-autosubmit>
        <?php foreach ($periodeList as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $periodeId === (int) $item['id'] ? 'selected' : '' ?>>
                <?= e((string) $item['nama']) ?> · <?= e((string) $item['status']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <a class="btn" href="<?= e(url('tax')) ?>"><?= icon('receipt', 15) ?> Dokumen Pajak</a>
    <a class="btn btn--primary" href="<?= e(url('tax/efaktur') . '?periode=' . $periodeId) ?>"><?= icon('download', 15) ?> Unduh e-Faktur CSV</a>
</form>

<?php if ($periode === null): ?>
    <div class="card">
        <div class="empty-state">
            <h3>Periode belum dipilih</h3>
            <p>Pilih periode akuntansi untuk melihat rekap pajak.</p>
        </div>
    </div>
<?php else: ?>
    <div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <div class="summary-item">
            <div class="summary-label">PPN Keluaran</div>
            <div class="summary-value"><?= e(rupiah($ppnKeluaran)) ?></div>
            <div class="tiny muted">DPP <?= e(rupiah_short((float) ($perTipe['faktur_keluaran']['dpp'] ?? 0))) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">PPN Masukan</div>
            <div class="summary-value"><?= e(rupiah($ppnMasukan)) ?></div>
            <div class="tiny muted">Kredit pajak</div>
        </div>
        <div class="summary-item">
            <div class="summary-label"><?= $kurangBayar >= 0 ? 'PPN Kurang Bayar' : 'PPN Lebih Bayar' ?></div>
            <div class="summary-value <?= $kurangBayar >= 0 ? 'summary-value--down' : 'summary-value--up' ?>"><?= e(rupiah(abs($kurangBayar))) ?></div>
            <div class="tiny muted">Keluaran − masukan</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total PPh</div>
            <div class="summary-value"><?= e(rupiah($totalPph)) ?></div>
            <div class="tiny muted">Bukti potong &amp; SSP</div>
        </div>
    </div>

    <div class="grid-main mb-16">
        <div class="card card--flush">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Rekap per Tipe Dokumen</h2>
                    <div class="card-sub"><?= e((string) $periode['nama']) ?> · dokumen void tidak dihitung</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipe</th>
                            <th class="num" style="width:70px">Dok</th>
                            <th class="num" style="width:150px">DPP</th>
                            <th class="num" style="width:140px">Pajak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipeList as $kode => $label): ?>
                            <?php $item = $perTipe[$kode] ?? ['jumlah' => 0, 'dpp' => 0, 'pajak' => 0]; ?>
                            <tr>
                                <td class="small"><?= e($label) ?></td>
                                <td class="num small"><?= (int) $item['jumlah'] ?></td>
                                <td class="num"><?= e(rupiah((float) $item['dpp'])) ?></td>
                                <td class="num strong"><?= e(rupiah((float) $item['pajak'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card--flush">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Rekap per Jenis Pajak</h2>
                    <div class="card-sub">Termasuk jenis tanpa dokumen pada periode ini</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th class="num" style="width:60px">Tarif</th>
                            <th class="num" style="width:140px">DPP</th>
                            <th class="num" style="width:130px">Pajak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perJenis as $jenis): ?>
                            <tr>
                                <td class="small">
                                    <?= e((string) $jenis['kode']) ?>
                                    <div class="tiny muted"><?= e((string) $jenis['nama']) ?></div>
                                </td>
                                <td class="num small"><?= e(number_format((float) $jenis['tarif'], 2, ',', '.')) ?>%</td>
                                <td class="num"><?= e(rupiah((float) $jenis['dpp'])) ?></td>
                                <td class="num strong"><?= e(rupiah((float) $jenis['pajak'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">SPT Periode <?= e((string) $periode['nama']) ?></h2>
                <div class="card-sub">Hitung ulang rekap, lalu tandai status pelaporan</div>
            </div>
            <?php if ($canManage): ?>
                <form class="row" method="post" action="<?= e(url('tax/report/generate')) ?>" style="gap:6px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
                    <select class="select select--sm" name="jenis" style="width:150px">
                        <?php foreach ($reportJenis as $kode => $label): ?>
                            <option value="<?= e($kode) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('calculator', 14) ?> Hitung Rekap</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:180px">Jenis SPT</th>
                        <th class="num" style="width:140px">DPP</th>
                        <th class="num" style="width:135px">Pajak</th>
                        <th class="num" style="width:135px">Kredit</th>
                        <th class="num" style="width:140px">Kurang Bayar</th>
                        <th style="width:100px">Status</th>
                        <th style="width:150px">Tgl Lapor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportJenis as $kode => $label): ?>
                        <?php
                        $report = $sudahDibuat[$kode] ?? null;
                        ?>
                        <tr>
                            <td class="small strong"><?= e($label) ?></td>
                            <td class="num"><?= $report !== null ? e(rupiah((float) $report['total_dpp'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= $report !== null ? e(rupiah((float) $report['total_pajak'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= $report !== null ? e(rupiah((float) $report['total_kredit'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num strong"><?= $report !== null ? e(rupiah((float) $report['kurang_bayar'])) : '<span class="muted">—</span>' ?></td>
                            <td>
                                <?php if ($report !== null): ?>
                                    <?= status_badge((string) $report['status']) ?>
                                <?php else: ?>
                                    <span class="badge badge--netral">Belum dihitung</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($report !== null && $canManage): ?>
                                    <form method="post" action="<?= e(url('tax/report/status')) ?>" class="row" style="gap:4px">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
                                        <input type="hidden" name="periode_id" value="<?= $periodeId ?>">
                                        <select class="select select--sm" name="status" style="width:92px">
                                            <?php foreach (['draft' => 'Draft', 'final' => 'Final', 'dilaporkan' => 'Lapor'] as $value => $statusLabel): ?>
                                                <option value="<?= e($value) ?>" <?= (string) $report['status'] === $value ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="input input--sm" type="date" name="tanggal_lapor" style="width:120px"
                                               value="<?= e((string) ($report['tanggal_lapor'] ?? '')) ?>">
                                        <button class="btn btn--sm" type="submit" title="Simpan status"><?= icon('check', 13) ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="small muted"><?= $report !== null ? e((string) ($report['tanggal_lapor'] ?? '—')) : '—' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Data e-Faktur Keluaran</h2>
                <div class="card-sub"><?= count($efaktur) ?> faktur siap diimpor ke aplikasi e-Faktur DJP</div>
            </div>
            <a class="btn btn--sm" href="<?= e(url('tax/efaktur') . '?periode=' . $periodeId) ?>"><?= icon('download', 14) ?> Unduh CSV</a>
        </div>
        <?php if ($efaktur === []): ?>
            <div class="empty-state">
                <div class="icon-wrap"><?= icon('receipt', 22) ?></div>
                <h3>Belum ada faktur keluaran</h3>
                <p>Catat dokumen bertipe <strong>Faktur Keluaran</strong> pada periode ini untuk menghasilkan CSV e-Faktur.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:180px">Nomor Faktur</th>
                            <th>Partner</th>
                            <th style="width:150px">NPWP</th>
                            <th style="width:105px">Tanggal</th>
                            <th class="num" style="width:140px">DPP</th>
                            <th class="num" style="width:90px">Tarif</th>
                            <th class="num" style="width:130px">PPN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($efaktur as $row): ?>
                            <tr>
                                <td class="mono small"><?= e((string) $row['nomor']) ?></td>
                                <td class="small"><?= e((string) ($row['partner_nama'] ?? '—')) ?></td>
                                <td class="mono small"><?= e((string) ($row['npwp'] ?? '—')) ?></td>
                                <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                                <td class="num"><?= e(rupiah((float) $row['dpp'])) ?></td>
                                <td class="num small"><?= e(number_format((float) $row['tarif'], 2, ',', '.')) ?>%</td>
                                <td class="num strong"><?= e(rupiah((float) $row['nilai_pajak'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
