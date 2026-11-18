<?php
/**
 * Detail proyek: profitabilitas, kontrak, tagihan, dan biaya.
 *
 * @var array $project
 * @var array $contracts
 * @var array $recognitions
 * @var array $invoices
 * @var array $beban
 * @var array $statusList
 * @var bool $canManage
 */
use Lju\Services\ProjectService;

$laba = (float) ($project['laba'] ?? 0);
$margin = (float) ($project['margin'] ?? 0);
$progress = (float) ($project['progress'] ?? 0);
$totalBeban = array_sum(array_map(static fn ($r) => (float) $r['nilai'], $beban));
$badgeStatus = static function (string $status): string {
    return match ($status) {
        'berjalan', 'paid', 'posted', 'aktif', 'selesai' => 'sukses',
        'batal', 'void', 'overdue' => 'bahaya',
        'partial', 'diajukan', 'submitted' => 'warning',
        default => 'netral',
    };
};
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(5,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Nilai Kontrak</div>
        <div class="summary-value"><?= e(rupiah_short((float) $project['nilai_kontrak'])) ?></div>
        <div class="tiny muted"><?= (int) $project['jumlah_kontrak'] ?> kontrak tercatat</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pendapatan Diakui</div>
        <div class="summary-value"><?= e(rupiah_short((float) $project['pendapatan_diakui'])) ?></div>
        <div class="tiny muted"><?= e(number_format($progress, 1, ',', '.')) ?>% kontrak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Biaya Proyek</div>
        <div class="summary-value"><?= e(rupiah_short((float) $project['biaya_jurnal'])) ?></div>
        <div class="tiny muted">Jurnal beban posted</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Laba Proyek</div>
        <div class="summary-value <?= $laba >= 0 ? 'summary-value--up' : 'summary-value--down' ?>"><?= e(rupiah_short($laba)) ?></div>
        <div class="tiny muted">Margin <?= e(number_format($margin, 1, ',', '.')) ?>%</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sisa Kontrak</div>
        <div class="summary-value"><?= e(rupiah_short((float) $project['sisa_kontrak'])) ?></div>
        <div class="tiny muted">Belum diakui sebagai pendapatan</div>
    </div>
</div>

<div class="grid-main mb-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Proyek</h2>
                <div class="card-sub">Dimensi akuntansi yang dipakai seluruh transaksi</div>
            </div>
            <span class="badge badge--<?= (string) $project['status'] === 'berjalan' ? 'sukses' : ((string) $project['status'] === 'batal' ? 'bahaya' : 'netral') ?>">
                <?= e($statusList[(string) $project['status']] ?? (string) $project['status']) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <div class="field-label">Pemberi Kerja</div>
                    <div class="strong small"><?= e((string) ($project['partner_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Project Manager</div>
                    <div class="strong small"><?= e((string) ($project['manager_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Unit Usaha</div>
                    <div class="small"><?= e((string) ($project['business_unit_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Bidang / Divisi</div>
                    <div class="small"><?= e((string) ($project['bidang_nama'] ?? '—')) ?> / <?= e((string) ($project['divisi_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Periode</div>
                    <div class="small">
                        <?= e($project['tanggal_mulai'] ? date('d M Y', strtotime((string) $project['tanggal_mulai'])) : '—') ?>
                        s.d.
                        <?= e($project['tanggal_selesai'] ? date('d M Y', strtotime((string) $project['tanggal_selesai'])) : '—') ?>
                    </div>
                </div>
                <div class="field">
                    <div class="field-label">Uang Muka</div>
                    <div class="small"><?= e(number_format((float) $project['uang_muka_persen'], 2, ',', '.')) ?>%</div>
                </div>
            </div>
            <?php if (!empty($project['keterangan'])): ?>
                <div class="notice notice--info mt-12">
                    <?= icon('info', 15) ?>
                    <div class="tiny"><?= e((string) $project['keterangan']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Realisasi Penagihan</h2>
                <div class="card-sub">Invoice AR atas nama proyek ini</div>
            </div>
            <span class="pill"><?= count($invoices) ?> invoice</span>
        </div>
        <div class="card-body">
            <div class="row" style="justify-content:space-between;margin-bottom:6px">
                <span class="small strong">Tagihan Terbit</span>
                <span class="small mono"><?= e(rupiah((float) $project['nilai_tagihan'])) ?></span>
            </div>
            <div class="progress-track progress-track--success">
                <span style="width:<?= (float) $project['nilai_kontrak'] > 0 ? max(0, min(100, (int) round((float) $project['nilai_tagihan'] / (float) $project['nilai_kontrak'] * 100))) : 0 ?>%"></span>
            </div>
            <div class="tiny muted mt-8">Terhadap nilai kontrak <?= e(rupiah((float) $project['nilai_kontrak'])) ?></div>

            <div class="mt-16">
                <?php foreach (array_slice($invoices, 0, 5) as $invoice): ?>
                    <div class="row" style="justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)">
                        <a class="small strong" href="<?= e(url('ar/' . (int) $invoice['id'])) ?>"><?= e((string) $invoice['nomor']) ?></a>
                        <span class="tiny muted"><?= e(date('d M Y', strtotime((string) $invoice['tanggal']))) ?></span>
                        <span class="badge badge--<?= $badgeStatus((string) $invoice['status']) ?>"><?= e(ucfirst((string) $invoice['status'])) ?></span>
                        <span class="small mono"><?= e(rupiah((float) $invoice['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($invoices === []): ?>
                    <p class="muted small">Belum ada tagihan untuk proyek ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid-main">
    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Kontrak &amp; Termin</h2>
                <div class="card-sub">Buka kontrak untuk menagih termin menjadi invoice AR</div>
            </div>
            <?php if ($canManage): ?>
                <a class="btn" href="<?= e(url('project/contract/create', ['project' => (int) $project['id']])) ?>"><?= icon('plus', 15) ?> Kontrak</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th>Judul</th>
                        <th class="num" style="width:140px">Nilai</th>
                        <th class="num" style="width:130px">Ditagih</th>
                        <th style="width:90px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><a class="mono strong" href="<?= e(url('project/contract/' . (int) $contract['id'])) ?>"><?= e((string) $contract['nomor']) ?></a></td>
                            <td class="small"><?= e((string) $contract['judul']) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $contract['nilai_kontrak'])) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $contract['ditagih'])) ?></td>
                            <td><span class="badge badge--<?= $badgeStatus((string) $contract['status']) ?>"><?= e(ucfirst((string) $contract['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($contracts === []): ?>
                        <tr><td colspan="5"><p class="muted small" style="padding:16px">Belum ada kontrak. Tambahkan kontrak agar termin dapat ditagih.</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card--flush">
        <div class="card-head">
            <div>
                <h2 class="card-title">Komposisi Biaya</h2>
                <div class="card-sub">Akun beban dengan nilai terbesar</div>
            </div>
            <span class="pill mono"><?= e(rupiah_short($totalBeban)) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th style="width:90px">Akun</th><th>Nama Akun</th><th class="num" style="width:130px">Nilai</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($beban as $item): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $item['kode']) ?></td>
                            <td class="small"><?= e((string) $item['nama']) ?></td>
                            <td class="num mono"><?= e(rupiah((float) $item['nilai'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($beban === []): ?>
                        <tr><td colspan="3"><p class="muted small" style="padding:16px">Belum ada biaya yang tercatat pada proyek ini.</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card--flush mt-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Pengakuan Pendapatan</h2>
            <div class="card-sub">Riwayat pengakuan pendapatan proyek dan jurnal terkait</div>
        </div>
        <a class="btn" href="<?= e(url('revenue')) ?>"><?= icon('chart', 15) ?> Modul Pendapatan</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:150px">Nomor</th>
                    <th style="width:110px">Tanggal</th>
                    <th>Metode</th>
                    <th class="num" style="width:80px">%</th>
                    <th class="num" style="width:140px">Nilai</th>
                    <th style="width:100px">Status</th>
                    <th style="width:140px">Jurnal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recognitions as $rec): ?>
                    <tr>
                        <td class="mono small"><?= e((string) $rec['nomor']) ?></td>
                        <td class="small"><?= e(date('d M Y', strtotime((string) $rec['tanggal']))) ?></td>
                        <td class="small"><?= e(str_replace('_', ' ', (string) $rec['metode'])) ?></td>
                        <td class="num small"><?= e(number_format((float) $rec['persentase'], 2, ',', '.')) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $rec['nilai'])) ?></td>
                        <td><span class="badge badge--<?= $badgeStatus((string) $rec['status']) ?>"><?= e(ucfirst((string) $rec['status'])) ?></span></td>
                        <td class="small"><?= e((string) ($rec['journal_nomor'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recognitions === []): ?>
                    <tr><td colspan="7"><p class="muted small" style="padding:16px">Belum ada pengakuan pendapatan untuk proyek ini.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
