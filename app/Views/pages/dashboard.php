<?php
/**
 * Executive Dashboard.
 *
 * @var array $data
 */

$likuiditas = (float) $data['likuiditas'];
$piutang = (float) $data['piutang'];
$hutang = (float) $data['hutang'];
$banks = $data['banks'];
$rkap = $data['rkap'];
$pagu = (float) $rkap['pagu'];
$realisasi = (float) $rkap['realisasi'];
$komitmen = (float) $rkap['komitmen'];
$serapan = (float) $data['serapan'];
$sisaAnggaran = max(0.0, $pagu - $realisasi);
$aging = $data['aging'];
$buckets = $aging['buckets'];
$arTotal = (float) $aging['ar_total'];
$apTotal = (float) $aging['ap_total'];
$arLancar = (float) ($buckets[0]['ar'] ?? 0);
$arLancarPct = $arTotal > 0 ? ($arLancar / $arTotal) * 100 : 0.0;
$rasio = $data['rasio'];
$ringkasan = $data['ringkasan'];
$approvals = $data['approvals'];
$audits = $data['audits'];
$counts = $data['counts'];
$tahun = (int) $data['tahun'];
$bankAktif = (int) $data['bank_aktif'];

/** Chip SLA dari deadline. */
$slaChip = static function (?string $deadline): string {
    if (empty($deadline)) {
        return '<span class="sla">Tanpa batas</span>';
    }
    $ts = strtotime($deadline);
    if ($ts === false) {
        return '<span class="sla">-</span>';
    }
    $diff = $ts - time();

    if ($diff <= 0) {
        return '<span class="sla sla--urgent"><span class="dot"></span>Melewati batas</span>';
    }
    if ($diff <= 7200) {
        return '<span class="sla sla--urgent"><span class="dot"></span>' . max(1, (int) round($diff / 3600)) . ' Jam Lagi</span>';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return '<span class="sla sla--soon">Hari ini, ' . date('H:i', $ts) . '</span>';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('+1 day'))) {
        return '<span class="sla sla--soon">Besok, ' . date('H:i', $ts) . '</span>';
    }
    return '<span class="sla">' . e(tanggal($ts, 'd M Y')) . '</span>';
};

/** Logo/inisial bank. */
$bankLogo = static function (array $bank): array {
    $code = strtoupper((string) ($bank['kode'] ?? ''));
    $tipe = (string) ($bank['tipe'] ?? 'bank');

    if (str_contains($code, 'BCA')) {
        return ['BCA', 'bank-logo--bca'];
    }
    if (str_contains($code, 'BNI')) {
        return ['BNI', 'bank-logo--bni'];
    }
    if (str_contains($code, 'MDR') || stripos((string) ($bank['nama_bank'] ?? ''), 'mandiri') !== false) {
        return ['BMRI', 'bank-logo--mandiri'];
    }
    if (in_array($tipe, ['kas', 'petty_cash'], true)) {
        return ['KAS', 'bank-logo--kas'];
    }
    $source = preg_replace('/[^A-Z]/', '', strtoupper((string) ($bank['nama_bank'] ?? $bank['nama'] ?? ''))) ?? '';
    return [mb_substr($source !== '' ? $source : 'BNK', 0, 4), ''];
};

$auditIcon = static function (string $action): array {
    return match (strtolower($action)) {
        'post'    => ['check-circle', 'success'],
        'approve' => ['check-circle', 'success'],
        'create'  => ['plus', 'primary'],
        'update'  => ['edit', 'warning'],
        'close'   => ['lock', 'warning'],
        'export'  => ['download', 'primary'],
        'refresh' => ['refresh', 'primary'],
        'login'   => ['user', 'primary'],
        'reject'  => ['x', 'danger'],
        'reverse' => ['refresh', 'danger'],
        default   => ['info', 'primary'],
    };
};
?>
<!-- ================= KPI ================= -->
<section class="kpi-grid">
    <article class="card kpi">
        <div class="kpi-top">
            <span class="kpi-label">Likuiditas Kas &amp; Bank</span>
            <span class="kpi-icon"><?= icon('bank', 16) ?></span>
        </div>
        <div class="kpi-value"><span class="cur">Rp</span><?= e(angka($likuiditas)) ?></div>
        <div class="kpi-foot">
            <span><?= $bankAktif ?> rekening aktif</span>
            <span class="ok">Saldo gabungan</span>
        </div>
    </article>

    <article class="card kpi">
        <div class="kpi-top">
            <span class="kpi-label">Realisasi Pagu RKAP <?= e((string) $tahun) ?></span>
            <span class="kpi-icon kpi-icon--info"><?= icon('chart', 16) ?></span>
        </div>
        <div class="kpi-value"><?= e(persen($serapan)) ?> <span class="cur" style="font-size:.48em">Terserap</span></div>
        <div class="progress progress--<?= e(progress_variant($serapan)) ?>">
            <span style="width: <?= e((string) min(100, max(0, $serapan))) ?>%"></span>
        </div>
        <div class="kpi-foot">
            <span>Pagu <strong class="strong"><?= e(rupiah_short($pagu)) ?></strong></span>
            <span>Realisasi <strong class="strong"><?= e(rupiah_short($realisasi)) ?></strong></span>
        </div>
        <div class="kpi-foot">
            <span>Komitmen PO</span>
            <span><?= e(rupiah_short($komitmen)) ?></span>
        </div>
    </article>

    <article class="card kpi">
        <div class="kpi-top">
            <span class="kpi-label">Piutang Usaha (AR)</span>
            <span class="kpi-icon kpi-icon--success"><?= icon('arrow-up', 16) ?></span>
        </div>
        <div class="kpi-value"><span class="cur">Rp</span><?= e(angka($piutang)) ?></div>
        <div class="kpi-foot">
            <span><span class="<?= $arLancarPct >= 70 ? 'ok' : 'warn' ?>"><?= e(persen($arLancarPct)) ?></span> lancar</span>
            <span><?= e($counts['customer']) ?> pelanggan aktif</span>
        </div>
        <div class="kpi-foot">
            <span>Jatuh tempo &gt; 30 hari</span>
            <span class="warn"><?= e(rupiah_short((float) $aging['ar_over_30'])) ?></span>
        </div>
    </article>

    <article class="card kpi">
        <div class="kpi-top">
            <span class="kpi-label">Hutang Usaha (AP)</span>
            <span class="kpi-icon kpi-icon--warning"><?= icon('arrow-down', 16) ?></span>
        </div>
        <div class="kpi-value"><span class="cur">Rp</span><?= e(angka($hutang)) ?></div>
        <div class="kpi-foot">
            <span>Rasio likuiditas</span>
            <span class="strong"><?= e(angka($rasio['current'], 2)) ?>x
                <span class="badge badge--<?= e($rasio['variant']) ?>" style="margin-left:4px"><?= e($rasio['label']) ?></span>
            </span>
        </div>
        <div class="kpi-foot">
            <span>Jatuh tempo ≤ 14 hari</span>
            <span class="bad"><?= e(rupiah_short((float) $aging['ap_due_14'])) ?></span>
        </div>
    </article>
</section>

<!-- ================= RINGKASAN KEUANGAN ================= -->
<section class="summary-strip">
    <div class="summary-item">
        <div class="summary-label">Total Aset</div>
        <div class="summary-value"><?= e(rupiah_short((float) $ringkasan['total_aset'])) ?></div>
        <div class="tiny muted">Kas <?= e(rupiah_short($likuiditas)) ?> · AR <?= e(rupiah_short($piutang)) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Total Kewajiban</div>
        <div class="summary-value"><?= e(rupiah_short((float) $ringkasan['total_kewajiban'])) ?></div>
        <div class="tiny muted">Ekuitas <?= e(rupiah_short((float) $ringkasan['ekuitas'])) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pendapatan YTD</div>
        <div class="summary-value summary-value--up"><?= e(rupiah_short((float) $ringkasan['pendapatan'])) ?></div>
        <div class="tiny muted">Tahun buku <?= e((string) $tahun) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Beban YTD</div>
        <div class="summary-value"><?= e(rupiah_short((float) $ringkasan['beban'])) ?></div>
        <div class="tiny muted">Sisa pagu <?= e(rupiah_short($sisaAnggaran)) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Laba / Rugi Berjalan</div>
        <div class="summary-value <?= ((float) $ringkasan['laba'] >= 0) ? 'summary-value--up' : 'summary-value--down' ?>">
            <?= e(rupiah_short((float) $ringkasan['laba'])) ?>
        </div>
        <div class="tiny muted"><?= ((float) $ringkasan['laba'] >= 0) ? 'Surplus' : 'Defisit' ?> operasional</div>
    </div>
</section>

<!-- ================= RKAP & KAS ================= -->
<section class="grid-main">
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Monitoring Realisasi RKAP <?= e((string) $tahun) ?> per Divisi</h2>
                <div class="card-sub">Perbandingan pagu anggaran teralokasi versus serapan aktual operasional.</div>
            </div>
            <a class="card-link" href="<?= e(url('budget')) ?>">Detail RKAP <?= icon('arrow-right', 14) ?></a>
        </div>
        <div class="card-body">
            <?php if ($rkap['divisi'] === []): ?>
                <div class="empty-state">
                    <div class="icon-wrap"><?= icon('chart', 22) ?></div>
                    <h3>Belum ada pagu anggaran</h3>
                    <p>RKAP tahun <?= e((string) $tahun) ?> belum disusun. Modul penyusunan RKAP tersedia pada Tahap 5.</p>
                    <a class="btn btn--sm" href="<?= e(url('budget')) ?>">Lihat modul anggaran</a>
                </div>
            <?php else: ?>
                <div class="rkp-list">
                    <?php foreach ($rkap['divisi'] as $row): ?>
                        <?php
                        $persen = (float) $row['persen'];
                        $variant = $persen >= 90 ? 'danger' : ($persen >= 75 ? 'warning' : 'primary');
                        ?>
                        <div class="rkp-row">
                            <div class="rkp-head">
                                <div class="rkp-name">
                                    Divisi <?= e((string) $row['nama']) ?>
                                    <span class="rkp-cc">Cost Center #<?= e(str_replace('CC-', '', (string) ($row['cc_kode'] ?? '-'))) ?></span>
                                </div>
                                <div class="rkp-amounts">
                                    <span class="rkp-real"><?= e(rupiah_short((float) $row['realisasi'])) ?></span>
                                    <span class="rkp-pagu">/ <?= e(rupiah_short((float) $row['pagu'])) ?></span>
                                    <span class="rkp-pct rkp-pct--<?= e($variant) ?>"><?= e(persen($persen)) ?></span>
                                </div>
                            </div>
                            <div class="progress progress--<?= e($variant) ?>">
                                <span style="width: <?= e((string) min(100, max(0, $persen))) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="rkp-total">
                    <span>
                        <?= icon('info', 14) ?>
                        Total Komitmen Pagu Tersedia: <strong><?= e(rupiah($pagu)) ?></strong>
                        (termasuk cadangan kontinjensi)
                    </span>
                    <span class="split">
                        <span>Terserap YTD: <strong><?= e(persen($serapan)) ?></strong></span>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Saldo Kas &amp; Bank Terkini</h2>
                <div class="card-sub">Posisi likuiditas kas operasional &amp; giro penyimpanan.</div>
            </div>
            <a class="card-link" href="<?= e(url('cash')) ?>">Transfer Kas</a>
        </div>
        <div class="card-body">
            <?php if ($banks === []): ?>
                <div class="empty-state">
                    <div class="icon-wrap"><?= icon('bank', 22) ?></div>
                    <h3>Belum ada rekening</h3>
                    <p>Tambahkan rekening bank atau kas pada master data.</p>
                    <a class="btn btn--sm" href="<?= e(url('master/bank-account/create')) ?>">Tambah rekening</a>
                </div>
            <?php else: ?>
                <div class="bank-list">
                    <?php foreach ($banks as $bank): ?>
                        <?php [$logo, $logoClass] = $bankLogo($bank); ?>
                        <div class="bank-row">
                            <div class="bank-logo <?= e($logoClass) ?>"><?= e($logo) ?></div>
                            <div class="bank-info">
                                <div class="bank-name"><?= e((string) $bank['nama']) ?></div>
                                <div class="bank-acc">
                                    <?= e((string) ($bank['nomor_rekening'] ?? '-')) ?>
                                    · <?= e(strtoupper((string) ($bank['mata_uang'] ?? 'IDR'))) ?>
                                </div>
                            </div>
                            <div>
                                <div class="bank-amount"><?= e(rupiah((float) $bank['saldo_berjalan'])) ?></div>
                                <div class="bank-tag <?= $bank['tipe'] === 'petty_cash' ? 'bank-tag--warning' : '' ?>">
                                    <?php if ($bank['tipe'] === 'petty_cash'): ?>
                                        Batas <?= e(rupiah_short((float) $bank['saldo_minimum'] * 40)) ?>
                                    <?php elseif ($bank['tipe'] === 'escrow'): ?>
                                        Holding proyek
                                    <?php else: ?>
                                        Operasional
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="divider"></div>
                <div class="row-between">
                    <span class="small muted"><?= icon('wallet', 14) ?> Semua rekening &amp; kas induk</span>
                    <span class="small strong">Total <?= e(rupiah_short($likuiditas)) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </article>
</section>

<!-- ================= AGING AR vs AP ================= -->
<section class="card mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Analisis Matriks Aging: Piutang (AR) vs Hutang (AP)</h2>
            <div class="card-sub">Jadwal jatuh tempo arus kas untuk menjaga stabilitas modal kerja bersih (net working capital).</div>
        </div>
        <div class="ratio-box">
            <span class="small muted">Current Ratio:</span>
            <span class="ratio-value"><?= e(angka($rasio['current'], 2)) ?>x</span>
            <span class="badge badge--<?= e($rasio['variant']) ?>"><?= e($rasio['label']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="aging-grid">
            <?php foreach ($buckets as $index => $bucket): ?>
                <?php
                $variant = (string) $bucket['variant'];
                $arShare = $arTotal > 0 ? ((float) $bucket['ar'] / $arTotal) * 100 : 0;
                $apShare = $apTotal > 0 ? ((float) $bucket['ap'] / $apTotal) * 100 : 0;
                $cardClass = $variant === 'bahaya' ? 'aging-card--danger' : ($variant === 'warning' ? 'aging-card--warning' : '');
                ?>
                <div class="aging-card <?= e($cardClass) ?>">
                    <div class="aging-top">
                        <span class="aging-label"><?= e((string) $bucket['label']) ?></span>
                        <span class="aging-dot <?= $index > 0 ? 'aging-dot--' . e($variant) : '' ?>"></span>
                    </div>

                    <div class="aging-line">
                        <span class="lbl">Piutang (AR)</span>
                        <span class="val"><?= e(rupiah_short((float) $bucket['ar'])) ?></span>
                    </div>
                    <div class="progress progress--sm progress--primary mb-8"><span style="width:<?= e((string) min(100, $arShare)) ?>%"></span></div>

                    <div class="aging-line">
                        <span class="lbl">Hutang (AP)</span>
                        <span class="val"><?= e(rupiah_short((float) $bucket['ap'])) ?></span>
                    </div>
                    <div class="progress progress--sm progress--info mb-8"><span style="width:<?= e((string) min(100, $apShare)) ?>%"></span></div>

                    <div class="aging-note"><?= e((string) $bucket['note']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= APPROVAL & AUDIT ================= -->
<section class="grid-main">
    <article class="card">
        <div class="card-head">
            <div class="row" style="gap:10px">
                <span class="kpi-icon"><?= icon('check-circle', 16) ?></span>
                <div>
                    <h2 class="card-title">Antrean Persetujuan Eksekutif</h2>
                    <div class="card-sub"><?= count($approvals) ?> dokumen finansial membutuhkan otorisasi berjenjang.</div>
                </div>
            </div>
            <?php if (($data['approval_count'] ?? 0) > 0): ?>
                <span class="badge badge--bahaya"><?= (int) $data['approval_count'] ?> Urgent</span>
            <?php endif; ?>
        </div>

        <?php if ($approvals === []): ?>
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon-wrap"><?= icon('check-circle', 22) ?></div>
                    <h3>Tidak ada antrean</h3>
                    <p>Semua pengajuan keuangan telah diproses. Antrean akan tampil otomatis saat ada dokumen baru.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Dokumen / Jenis</th>
                            <th>Pemohon &amp; Divisi</th>
                            <th class="num">Nilai Pengajuan</th>
                            <th>Batas Waktu (SLA)</th>
                            <th class="right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvals as $approval): ?>
                            <tr>
                                <td>
                                    <div class="doc-code"><?= e((string) $approval['nomor_dokumen']) ?></div>
                                    <div class="doc-title"><?= e(str_limit((string) $approval['judul'], 42)) ?></div>
                                </td>
                                <td>
                                    <div class="person">
                                        <span class="person-name"><?= e((string) ($approval['pemohon'] ?? 'Sistem')) ?></span>
                                        <span class="person-role"><?= e(str_limit((string) ($approval['divisi'] ?? 'Kantor Pusat'), 34)) ?></span>
                                    </div>
                                </td>
                                <td class="num strong"><?= e(rupiah((float) $approval['amount'])) ?></td>
                                <td><?= $slaChip($approval['deadline'] ?? null) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn--primary btn--xs" href="<?= e(url('approval')) ?>">Setujui</a>
                                        <a class="icon-btn" style="width:28px;height:28px" href="<?= e(url('approval')) ?>" aria-label="Detail"><?= icon('eye', 14) ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="list-foot">
                <span>Menampilkan <?= count($approvals) ?> dari <?= (int) ($data['approval_count'] ?? count($approvals)) ?> permintaan otorisasi</span>
                <a class="card-link" href="<?= e(url('approval')) ?>">Buka Seluruh Inbox Persetujuan <?= icon('arrow-right', 14) ?></a>
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Audit Log &amp; Mutasi Finansial</h2>
                <div class="card-sub">Feed transaksi riil dan posting jurnal terverifikasi.</div>
            </div>
            <a class="icon-btn" href="<?= e(url('system/audit')) ?>" aria-label="Lihat audit trail"><?= icon('history', 16) ?></a>
        </div>
        <div class="card-body">
            <?php if ($audits === []): ?>
                <div class="empty-state">
                    <div class="icon-wrap"><?= icon('history', 22) ?></div>
                    <h3>Belum ada aktivitas</h3>
                    <p>Setiap perubahan data akan tercatat otomatis di sini.</p>
                </div>
            <?php else: ?>
                <div class="feed">
                    <?php foreach ($audits as $log): ?>
                        <?php [$auditIconName, $auditVariant] = $auditIcon((string) $log['action']); ?>
                        <div class="feed-item">
                            <span class="feed-icon feed-icon--<?= e($auditVariant) ?>"><?= icon($auditIconName, 15) ?></span>
                            <div class="feed-body">
                                <div class="feed-title"><?= e(str_limit((string) ($log['description'] ?? ucfirst((string) $log['action'])), 64)) ?></div>
                                <div class="feed-meta">
                                    <?php if (!empty($log['ref_code'])): ?>
                                        <span class="code-pill"><?= e(str_limit((string) $log['ref_code'], 26)) ?></span>
                                    <?php endif; ?>
                                    <span><?= e(str_limit((string) $log['user_name'], 20)) ?></span>
                                </div>
                            </div>
                            <span class="feed-time"><?= e(waktu_relatif($log['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="audit-foot">
                    <?= icon('shield', 15) ?>
                    Audit Trail Immutable v<?= e((string) setting('app_version', '1.0.0')) ?>
                    <a href="<?= e(url('system/audit')) ?>" style="margin-left:auto;font-size:11.5px">Lihat semua</a>
                </div>
            <?php endif; ?>
        </div>
    </article>
</section>
