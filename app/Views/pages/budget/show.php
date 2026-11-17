<?php
/**
 * Detail RKAP + monitoring realisasi + komitmen.
 *
 * @var array $row
 * @var array $lines
 * @var array $monitoring
 * @var array $commitments
 * @var int $sampaiBulan
 * @var array $bulanList
 * @var array $jenis
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canApprove
 * @var bool $canActivate
 * @var bool $canRevise
 * @var bool $canArchive
 * @var bool $canDelete
 */
use Lju\Services\BudgetService;

$status = (string) $row['status'];
$id = (int) $row['id'];
$totals = $monitoring['totals'] ?? [];
$rows = $monitoring['rows'] ?? [];
$pendapatan = (float) $row['total_pendapatan'];
$beban = (float) $row['total_beban'];
$selisih = $pendapatan - $beban;
$serapan = (float) ($totals['persen_beban'] ?? 0);
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono"><?= e((string) $row['nomor']) ?></span>
                <?= status_badge($status) ?>
                <span class="badge badge--info">Versi <?= (int) $row['versi'] ?></span>
                <?php if (!empty($row['parent_nomor'])): ?>
                    <span class="badge badge--netral">Revisi dari <?= e((string) $row['parent_nomor']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['judul']) ?></h2>
            <div class="card-sub">
                Tahun anggaran <?= (int) $row['tahun'] ?>
                · <?= (int) $row['jumlah_baris'] ?> baris
                · dibuat <?= e((string) ($row['created_by_name'] ?? '—')) ?>
                <?= !empty($row['approved_by_name']) ? ' · disetujui ' . e((string) $row['approved_by_name']) : '' ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('budget/' . $id . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" action="<?= e(url('budget/' . $id . '/submit')) ?>" data-confirm="Ajukan RKAP ini untuk persetujuan?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('arrow-up', 14) ?> Ajukan</button>
                </form>
            <?php endif; ?>

            <?php if ($canApprove): ?>
                <form method="post" action="<?= e(url('budget/' . $id . '/approve')) ?>" data-confirm="Setujui RKAP ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check', 14) ?> Setujui</button>
                </form>
            <?php endif; ?>

            <?php if ($canActivate): ?>
                <form method="post" action="<?= e(url('budget/' . $id . '/activate')) ?>" data-confirm="Aktifkan RKAP ini sebagai pagu tahun <?= (int) $row['tahun'] ?>? Versi aktif lain akan diarsipkan.">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check-circle', 14) ?> Aktifkan</button>
                </form>
            <?php endif; ?>

            <?php if ($canRevise): ?>
                <form method="post" action="<?= e(url('budget/' . $id . '/revise')) ?>" data-confirm="Buat versi revisi baru dari RKAP ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm" type="submit"><?= icon('layers', 14) ?> Buat Revisi</button>
                </form>
            <?php endif; ?>

            <?php if ($canArchive || $canDelete || $canApprove): ?>
                <details class="action-pop">
                    <summary class="btn btn--sm"><?= icon('settings', 14) ?> Lainnya</summary>
                    <div class="action-pop-body">
                        <?php if ($canApprove): ?>
                            <form method="post" action="<?= e(url('budget/' . $id . '/reject')) ?>" class="stack-8">
                                <?= csrf_field() ?>
                                <label class="field-label">Tolak RKAP</label>
                                <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan penolakan" required></textarea>
                                <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Tolak</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canArchive): ?>
                            <form method="post" action="<?= e(url('budget/' . $id . '/archive')) ?>" style="margin-top:12px"
                                  data-confirm="Arsipkan RKAP aktif ini?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit"><?= icon('layers', 14) ?> Arsipkan</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url('budget/' . $id . '/delete')) ?>" style="margin-top:12px"
                                  data-confirm="Hapus RKAP ini secara permanen?">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($row['catatan'])): ?>
        <div class="card-body" style="padding-top:0">
            <div class="notice" style="margin:0">
                <?= icon('info', 16) ?>
                <div><?= e((string) $row['catatan']) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Pagu Pendapatan</div>
        <div class="summary-value"><?= e(rupiah($pendapatan)) ?></div>
        <div class="tiny muted">Realisasi <?= e(rupiah((float) ($totals['realisasi_pendapatan'] ?? 0))) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Pagu Beban</div>
        <div class="summary-value"><?= e(rupiah($beban)) ?></div>
        <div class="tiny muted">Realisasi <?= e(rupiah((float) ($totals['realisasi_beban'] ?? 0))) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Komitmen PO</div>
        <div class="summary-value"><?= e(rupiah((float) ($totals['komitmen_beban'] ?? 0))) ?></div>
        <div class="tiny muted">Pengadaan belum terealisasi</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Serapan Beban s.d. <?= e($bulanList[$sampaiBulan] ?? '') ?></div>
        <div class="summary-value<?= $serapan >= 100 ? ' summary-value--down' : '' ?>"><?= e(number_format($serapan, 1, ',', '.')) ?>%</div>
        <div class="tiny muted">Selisih pagu <?= e(rupiah($selisih)) ?></div>
    </div>
</div>

<div class="card mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Monitoring Realisasi</h2>
            <div class="card-sub">Realisasi dihitung dari jurnal posted tahun <?= (int) $row['tahun'] ?> sampai bulan terpilih.</div>
        </div>
        <form method="get" action="<?= e(url('budget/' . $id)) ?>" class="row" style="gap:8px">
            <input type="hidden" name="r" value="budget/<?= $id ?>">
            <select class="filter-select" name="bulan" data-autosubmit>
                <?php foreach ($bulanList as $nomor => $nama): ?>
                    <option value="<?= (int) $nomor ?>" <?= (int) $sampaiBulan === (int) $nomor ? 'selected' : '' ?>><?= e($nama) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--sm" type="submit"><?= icon('filter', 13) ?> Terapkan</button>
        </form>
    </div>

    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('chart', 22) ?></div>
            <h3>Belum ada baris anggaran</h3>
            <p>Ubah RKAP untuk menambahkan pagu per akun dan dimensi.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:230px">Akun</th>
                        <th style="width:110px">Jenis</th>
                        <th style="min-width:150px">Dimensi</th>
                        <th class="num" style="width:140px">Pagu</th>
                        <th class="num" style="width:140px">Realisasi</th>
                        <th class="num" style="width:130px">Komitmen</th>
                        <th class="num" style="width:130px">Sisa</th>
                        <th style="width:170px">Serapan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $line): ?>
                        <?php $warna = $line['status'] === 'over' ? 'amt-neg' : ''; ?>
                        <tr>
                            <td>
                                <div class="small"><span class="mono"><?= e($line['coa_kode']) ?></span> · <?= e($line['coa_nama']) ?></div>
                            </td>
                            <td><span class="badge badge--<?= $line['jenis'] === 'pendapatan' ? 'sukses' : 'netral' ?>"><?= e($jenis[$line['jenis']] ?? $line['jenis']) ?></span></td>
                            <td class="small"><?= $line['dimensi'] !== '' ? e($line['dimensi']) : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= e(rupiah($line['pagu'])) ?></td>
                            <td class="num strong"><?= e(rupiah($line['realisasi'])) ?></td>
                            <td class="num"><?= $line['komitmen'] > 0 ? e(rupiah($line['komitmen'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num<?= $line['sisa'] < 0 ? ' amt-neg' : '' ?>"><?= e(rupiah($line['sisa'])) ?></td>
                            <td>
                                <div class="row" style="justify-content:space-between;gap:8px">
                                    <span class="small <?= $warna ?>"><?= e(number_format($line['persen'], 1, ',', '.')) ?>%</span>
                                    <?= status_badge((string) $line['status']) ?>
                                </div>
                                <div class="progress-track" style="margin-top:6px">
                                    <span style="width:<?= (int) min(100, max(0, $line['persen'])) ?>%;background:<?= $line['status'] === 'over' ? 'var(--danger)' : ($line['status'] === 'waspada' ? 'var(--warning)' : 'var(--primary)') ?>"></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="right strong small">TOTAL</td>
                        <td class="num strong"><?= e(rupiah((float) ($totals['pagu_beban'] ?? 0) + (float) ($totals['pagu_pendapatan'] ?? 0))) ?></td>
                        <td class="num strong"><?= e(rupiah((float) ($totals['realisasi_beban'] ?? 0) + (float) ($totals['realisasi_pendapatan'] ?? 0))) ?></td>
                        <td class="num strong"><?= e(rupiah((float) ($totals['komitmen_beban'] ?? 0))) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<details class="card mb-16">
    <summary class="card-head" style="cursor:pointer">
        <div>
            <h2 class="card-title">Rincian Bulanan (12 Bulan)</h2>
            <div class="card-sub">Distribusi pagu per bulan · klik untuk membuka</div>
        </div>
        <span class="pill"><?= count($lines) ?> baris</span>
    </summary>
    <div class="table-wrap">
        <table class="table table--compact">
            <thead>
                <tr>
                    <th style="min-width:200px">Akun</th>
                    <th class="num" style="width:110px">Total</th>
                    <?php foreach ($bulanList as $nama): ?>
                        <th class="num" style="width:90px"><?= e(str_limit($nama, 3)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td class="small"><span class="mono"><?= e((string) $line['coa_kode']) ?></span> · <?= e((string) $line['coa_nama']) ?></td>
                        <td class="num strong"><?= e(rupiah((float) $line['total'])) ?></td>
                        <?php for ($bulan = 1; $bulan <= 12; $bulan++): ?>
                            <?php $nilai = (float) $line[sprintf('m%02d', $bulan)]; ?>
                            <td class="num small"><?= $nilai > 0 ? e(rupiah_short($nilai)) : '<span class="muted">—</span>' ?></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Komitmen Pengadaan</h2>
            <div class="card-sub"><?= count($commitments) ?> PO/kontrak yang mengikat RKAP ini</div>
        </div>
        <a class="btn btn--sm" href="<?= e(url('budget/commitments')) ?>"><?= icon('plus', 14) ?> Kelola Komitmen</a>
    </div>
    <?php if ($commitments === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('briefcase', 22) ?></div>
            <h3>Belum ada komitmen</h3>
            <p>Catat PO/kontrak pengadaan agar pagu yang sudah terikat terlihat pada monitoring.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:160px">Nomor PO</th>
                        <th style="width:110px">Tanggal</th>
                        <th>Keterangan</th>
                        <th style="width:180px">Vendor</th>
                        <th class="num" style="width:150px">Nilai</th>
                        <th style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commitments as $commitment): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $commitment['nomor_po']) ?></td>
                            <td class="small"><?= e(tanggal($commitment['tanggal'], 'd M Y')) ?></td>
                            <td class="small"><?= e(str_limit((string) ($commitment['keterangan'] ?? '—'), 70)) ?></td>
                            <td class="small"><?= e((string) ($commitment['partner_nama'] ?? '—')) ?></td>
                            <td class="num strong"><?= e(rupiah((float) $commitment['nilai'])) ?></td>
                            <td><?= status_badge((string) $commitment['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Informasi RKAP</h2>
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Nomor</dt><dd class="mono"><?= e((string) $row['nomor']) ?></dd>
                <dt>Tahun / Versi</dt><dd><?= (int) $row['tahun'] ?> · versi <?= (int) $row['versi'] ?></dd>
                <dt>Revisi dari</dt><dd><?= e((string) ($row['parent_nomor'] ?? '—')) ?></dd>
                <dt>Status</dt><dd><?= status_badge($status) ?></dd>
                <dt>Total Pendapatan</dt><dd><?= e(rupiah($pendapatan)) ?></dd>
                <dt>Total Beban</dt><dd><?= e(rupiah($beban)) ?></dd>
                <dt>Selisih</dt><dd class="<?= $selisih < 0 ? 'amt-neg' : '' ?>"><?= e(rupiah($selisih)) ?></dd>
                <dt>Dibuat oleh</dt><dd><?= e((string) ($row['created_by_name'] ?? '—')) ?></dd>
                <dt>Disetujui oleh</dt><dd><?= e((string) ($row['approved_by_name'] ?? '—')) ?><?= !empty($row['approved_at']) ? ' · ' . e(tanggal_waktu($row['approved_at'])) : '' ?></dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Alur Versi</h2>
        </div>
        <div class="card-body">
            <div class="timeline">
                <div class="timeline-item">
                    <span class="timeline-dot timeline-dot--primary"><?= icon('edit', 12) ?></span>
                    <div>
                        <div class="small strong">RKAP disusun</div>
                        <div class="tiny muted"><?= e(tanggal_waktu($row['created_at'] ?? null)) ?> · <?= e((string) ($row['created_by_name'] ?? '—')) ?></div>
                    </div>
                </div>
                <?php if (!empty($row['approved_at'])): ?>
                    <div class="timeline-item">
                        <span class="timeline-dot timeline-dot--sukses"><?= icon('check', 12) ?></span>
                        <div>
                            <div class="small strong">Disetujui</div>
                            <div class="tiny muted"><?= e(tanggal_waktu($row['approved_at'])) ?> · <?= e((string) ($row['approved_by_name'] ?? '—')) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($status === 'aktif'): ?>
                    <div class="timeline-item">
                        <span class="timeline-dot timeline-dot--info"><?= icon('check-circle', 12) ?></span>
                        <div>
                            <div class="small strong">Menjadi pagu aktif</div>
                            <div class="tiny muted">Dipakai sebagai pembanding realisasi tahun <?= (int) $row['tahun'] ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($status === 'arsip'): ?>
                    <div class="timeline-item">
                        <span class="timeline-dot timeline-dot--netral"><?= icon('layers', 12) ?></span>
                        <div>
                            <div class="small strong">Diarsipkan</div>
                            <div class="tiny muted">Digantikan versi yang lebih baru</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
