<?php
/**
 * Hub laporan keuangan.
 *
 * @var array $ringkasan
 * @var array $labaRugi
 * @var array $neraca
 * @var array $arusKas
 * @var array $rasio
 * @var array $tren
 * @var array $periodeList
 * @var int $periodeId
 * @var array $jenisList
 * @var bool $canExport
 */
use Lju\Services\ReportService;

$periode = $ringkasan['periode'];
$kas = (float) ($arusKas['kas_akhir'] ?? 0);
$labaBersih = (float) ($labaRugi['laba_bersih'] ?? 0);
$margin = (float) ($labaRugi['margin'] ?? 0);

$laporanKartu = [
    [
        'jenis' => 'laba-rugi',
        'route' => 'report/income',
        'icon'  => 'chart',
        'desc'  => 'Pendapatan, beban, laba kotor, dan laba bersih beserta pembanding periode sebelumnya.',
        'nilai' => (float) ($labaRugi['pendapatan'] ?? 0),
        'label' => 'Pendapatan',
    ],
    [
        'jenis' => 'neraca',
        'route' => 'report/balance-sheet',
        'icon'  => 'scale',
        'desc'  => 'Posisi aset, kewajiban, dan ekuitas pada tanggal laporan beserta rasio likuiditas.',
        'nilai' => (float) ($neraca['total_aset'] ?? 0),
        'label' => 'Total aset',
    ],
    [
        'jenis' => 'arus-kas',
        'route' => 'report/cash-flow',
        'icon'  => 'wallet',
        'desc'  => 'Arus kas operasi, investasi, dan pendanaan serta rekonsiliasi metode tidak langsung.',
        'nilai' => (float) ($arusKas['neto'] ?? 0),
        'label' => 'Kas neto',
    ],
    [
        'jenis' => 'ekuitas',
        'route' => 'report/equity',
        'icon'  => 'layers',
        'desc'  => 'Perubahan ekuitas pemilik: saldo awal, laba periode, setoran/prive, dan saldo akhir.',
        'nilai' => (float) ($neraca['total_ekuitas'] ?? 0),
        'label' => 'Ekuitas akhir',
    ],
    [
        'jenis' => 'proyek',
        'route' => 'report/project',
        'icon'  => 'briefcase',
        'desc'  => 'Pendapatan dan beban per proyek beserta laba dan margin proyek pada periode.',
        'nilai' => null,
        'label' => 'Per proyek',
    ],
];
?>
<form class="toolbar mb-16" method="get" action="<?= e(url('report')) ?>">
    <input type="hidden" name="r" value="report">
    <select class="filter-select" name="periode" data-autosubmit>
        <?php foreach ($periodeList as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $periodeId === (int) $item['id'] ? 'selected' : '' ?>>
                <?= e((string) $item['nama']) ?> · <?= e((string) $item['status']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= e((string) $ringkasan['rentang']['label']) ?></span>
</form>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Total Aset</div>
        <div class="summary-value"><?= e(rupiah((float) ($neraca['total_aset'] ?? 0))) ?></div>
        <div class="tiny muted">Per <?= e(tanggal($ringkasan['rentang']['to'], 'd M Y')) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pendapatan Periode</div>
        <div class="summary-value"><?= e(rupiah((float) ($labaRugi['pendapatan'] ?? 0))) ?></div>
        <div class="tiny muted">Dibanding periode sebelumnya</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Laba Bersih</div>
        <div class="summary-value <?= $labaBersih >= 0 ? 'summary-value--up' : 'summary-value--down' ?>"><?= e(rupiah($labaBersih)) ?></div>
        <div class="tiny muted">Net margin <?= e(ReportService::pctLabel($margin)) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Kas &amp; Bank</div>
        <div class="summary-value"><?= e(rupiah($kas)) ?></div>
        <div class="tiny muted">Neto periode <?= e(rupiah((float) ($arusKas['neto'] ?? 0))) ?></div>
    </div>
</div>

<div class="grid-main mb-16">
    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Tren Pendapatan vs Beban</h2>
                <div class="card-sub">Enam bulan terakhir dari jurnal posted</div>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot legend-dot--rev"></i> Pendapatan</span>
                <span><i class="legend-dot legend-dot--exp"></i> Beban</span>
            </div>
        </div>
        <div class="card-body">
            <?php if ((float) $tren['maks'] <= 1): ?>
                <p class="muted small">Belum ada transaksi pendapatan/beban pada enam bulan terakhir.</p>
            <?php else: ?>
                <div class="trend-chart">
                    <?php foreach ($tren['bulan'] as $bulan): ?>
                        <?php
                        $rev = max(0.0, (float) $bulan['pendapatan']);
                        $exp = max(0.0, (float) $bulan['beban']);
                        $tinggiRev = (int) round($rev / (float) $tren['maks'] * 120);
                        $tinggiExp = (int) round($exp / (float) $tren['maks'] * 120);
                        ?>
                        <div class="trend-col">
                            <div class="trend-bars">
                                <span class="trend-bar trend-bar--rev" style="height:<?= max(2, $tinggiRev) ?>px" title="Pendapatan <?= e(rupiah($rev)) ?>"></span>
                                <span class="trend-bar trend-bar--exp" style="height:<?= max(2, $tinggiExp) ?>px" title="Beban <?= e(rupiah($exp)) ?>"></span>
                            </div>
                            <div class="trend-label"><?= e((string) $bulan['label']) ?></div>
                            <div class="trend-value <?= (float) $bulan['laba'] >= 0 ? 'trend--up' : 'trend--down' ?>">
                                <?= e(rupiah_short((float) $bulan['laba'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Perbandingan Bulan Ini</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Pendapatan</dt>
                <dd class="row" style="justify-content:space-between">
                    <span><?= e(rupiah((float) $tren['banding']['pendapatan_nilai'])) ?></span>
                    <?php $p = $tren['banding']['pendapatan']; ?>
                    <span class="trend <?= ($p ?? 0) >= 0 ? 'trend--up' : 'trend--down' ?>"><?= $p === null ? '—' : e(ReportService::pctLabel($p)) ?></span>
                </dd>
                <dt>Beban</dt>
                <dd class="row" style="justify-content:space-between">
                    <span><?= e(rupiah((float) $tren['banding']['beban_nilai'])) ?></span>
                    <?php $p = $tren['banding']['beban']; ?>
                    <span class="trend <?= ($p ?? 0) <= 0 ? 'trend--up' : 'trend--down' ?>"><?= $p === null ? '—' : e(ReportService::pctLabel($p)) ?></span>
                </dd>
                <dt>Laba Bersih</dt>
                <dd class="row" style="justify-content:space-between">
                    <span class="strong"><?= e(rupiah((float) $tren['banding']['laba_nilai'])) ?></span>
                    <?php $p = $tren['banding']['laba']; ?>
                    <span class="trend <?= ($p ?? 0) >= 0 ? 'trend--up' : 'trend--down' ?>"><?= $p === null ? '—' : e(ReportService::pctLabel($p)) ?></span>
                </dd>
            </dl>
            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">Perbandingan memakai periode bulan sebelumnya dengan panjang hari setara.</div>
            </div>
        </div>
    </div>
</div>

<div class="section-head mb-12">
    <div>
        <h2 class="card-title">Laporan Wajib</h2>
        <div class="card-sub">Semua laporan dihitung dari jurnal posted dan dapat dicetak / diekspor.</div>
    </div>
</div>

<div class="report-grid mb-16">
    <?php foreach ($laporanKartu as $kartu): ?>
        <a class="report-card" href="<?= e(url($kartu['route'], ['periode' => $periodeId])) ?>">
            <div class="report-card-icon"><?= icon((string) $kartu['icon'], 18) ?></div>
            <div class="report-card-body">
                <div class="report-card-title"><?= e(ReportService::JENIS[$kartu['jenis']]) ?></div>
                <div class="report-card-desc"><?= e((string) $kartu['desc']) ?></div>
            </div>
            <div class="report-card-meta">
                <span class="tiny muted"><?= e((string) $kartu['label']) ?></span>
                <?php if ($kartu['nilai'] !== null): ?>
                    <span class="small strong"><?= e(rupiah_short((float) $kartu['nilai'])) ?></span>
                <?php else: ?>
                    <span class="small strong">Lihat rincian</span>
                <?php endif; ?>
            </div>
            <span class="report-card-arrow"><?= icon('arrow-right', 15) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid-main">
    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Rasio Keuangan</h2>
                <div class="card-sub">Dihitung dari neraca &amp; laba rugi periode terpilih</div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Rasio</th>
                        <th style="width:120px">Nilai</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rasio as $item): ?>
                        <tr>
                            <td class="small strong"><?= e((string) $item['label']) ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $item['tone'] === 'up' ? 'sukses' : ((string) $item['tone'] === 'down' ? 'bahaya' : 'netral') ?>">
                                    <?= e(ReportService::formatNilai($item['nilai'], (string) $item['format'])) ?>
                                </span>
                            </td>
                            <td class="tiny muted"><?= e((string) $item['hint']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Akses Cepat</h2>
        </div>
        <div class="card-body">
            <div class="quick-links">
                <a class="quick-link" href="<?= e(url('trial-balance')) ?>"><?= icon('scale', 16) ?><span>Neraca Saldo</span><?= icon('arrow-right', 14) ?></a>
                <a class="quick-link" href="<?= e(url('ledger')) ?>"><?= icon('book', 16) ?><span>Buku Besar</span><?= icon('arrow-right', 14) ?></a>
                <a class="quick-link" href="<?= e(url('budget/monitoring')) ?>"><?= icon('chart', 16) ?><span>Realisasi Anggaran</span><?= icon('arrow-right', 14) ?></a>
                <a class="quick-link" href="<?= e(url('asset')) ?>"><?= icon('asset', 16) ?><span>Daftar Aset Tetap</span><?= icon('arrow-right', 14) ?></a>
                <a class="quick-link" href="<?= e(url('tax/report')) ?>"><?= icon('receipt', 16) ?><span>Rekap Pajak</span><?= icon('arrow-right', 14) ?></a>
                <a class="quick-link" href="<?= e(url('ar')) ?>"><?= icon('arrow-up', 16) ?><span>Aging Piutang</span><?= icon('arrow-right', 14) ?></a>
                <a class="quick-link" href="<?= e(url('ap')) ?>"><?= icon('arrow-down', 16) ?><span>Aging Hutang</span><?= icon('arrow-right', 14) ?></a>
            </div>
        </div>
    </div>
</div>
