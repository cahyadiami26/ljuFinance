<?php
/**
 * Tutup buku.
 *
 * @var int $tahun
 * @var array $years
 * @var array $periods
 * @var array $counts
 * @var array $totals
 * @var array|null $fiscal
 * @var array $neraca
 * @var array $labaRugi
 * @var array $riwayat
 * @var int $saldoRows
 * @var int $jurnalPosted
 * @var bool $canManage
 */
$fiscalStatus = (string) ($fiscal['status'] ?? 'open');
$semuaTertutup = true;
foreach ($periods as $period) {
    if ((string) $period['status'] === 'open') {
        $semuaTertutup = false;
        break;
    }
}
?>
<div class="toolbar">
    <div class="row" style="gap:6px">
        <?php foreach ($years as $year): ?>
            <a class="btn btn--sm <?= (int) $year === $tahun ? 'btn--primary' : '' ?>" href="<?= e(url('closing', ['tahun' => (int) $year])) ?>">
                <?= (int) $year ?>
            </a>
        <?php endforeach; ?>
    </div>
    <span class="spacer"></span>
    <?php if ($canManage): ?>
        <form method="post" action="<?= e(url('closing/sync')) ?>" data-confirm="Hitung ulang saldo akun dari seluruh jurnal posted?">
            <?= csrf_field() ?>
            <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
            <button class="btn btn--sm" type="submit"><?= icon('refresh', 14) ?> Sinkronkan Saldo</button>
        </form>
    <?php endif; ?>
</div>

<div class="grid-3 mb-16">
    <div class="card">
        <div class="card-body" style="padding-top:18px">
            <div class="summary-label">Tahun Buku</div>
            <div class="summary-value" style="font-size:22px"><?= (int) $tahun ?></div>
            <div class="row" style="gap:6px;margin-top:6px">
                <?= status_badge($fiscalStatus) ?>
                <span class="badge badge--netral"><?= (int) $jurnalPosted ?> jurnal posted</span>
            </div>
            <div class="tiny muted mt-8">
                <?= e(tanggal($fiscal['tanggal_mulai'] ?? ($tahun . '-01-01'))) ?> –
                <?= e(tanggal($fiscal['tanggal_selesai'] ?? ($tahun . '-12-31'))) ?>
            </div>
            <?php if ($canManage && $fiscalStatus === 'open'): ?>
                <details class="mt-12">
                    <summary class="btn btn--sm btn--dark" style="display:inline-flex"><?= icon('lock', 14) ?> Tutup Buku Tahunan</summary>
                    <form method="post" action="<?= e(url('closing/year')) ?>" class="stack-8 mt-12"
                          data-confirm="Jalankan tutup buku tahunan <?= (int) $tahun ?>? Jurnal penutup akan dibuat, seluruh periode dikunci permanen.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
                        <input class="input input--sm" type="text" name="catatan" placeholder="Catatan rapat tutup buku (opsional)" maxlength="200">
                        <button class="btn btn--sm btn--danger" type="submit">Konfirmasi Tutup Tahun</button>
                        <span class="tiny muted">Pastikan seluruh periode sudah ditutup dan seluruh jurnal sudah posted.</span>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:18px">
            <div class="summary-label">Laba / Rugi Tahun Berjalan</div>
            <div class="summary-value" style="font-size:20px;color:<?= $labaRugi['laba'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                <?= e(rupiah(abs($labaRugi['laba']))) ?>
                <span class="tiny muted" style="font-weight:600"><?= $labaRugi['laba'] >= 0 ? 'laba' : 'rugi' ?></span>
            </div>
            <dl class="kv mt-12" style="grid-template-columns:1fr auto">
                <dt>Pendapatan</dt><dd><?= e(rupiah(abs($labaRugi['pendapatan']))) ?></dd>
                <dt>Beban</dt><dd><?= e(rupiah(abs($labaRugi['beban']))) ?></dd>
            </dl>
            <div class="tiny muted mt-8">Dihitung dari jurnal posted sampai 31 Desember <?= (int) $tahun ?>.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:18px">
            <div class="summary-label">Keseimbangan Neraca</div>
            <div class="summary-value" style="font-size:20px">
                <?= $neraca['seimbang'] ? badge('Seimbang', 'sukses') : badge('Selisih ' . e(rupiah(abs($neraca['selisih']))), 'bahaya') ?>
            </div>
            <dl class="kv mt-12" style="grid-template-columns:1fr auto">
                <dt>Total debit</dt><dd><?= e(rupiah($neraca['total_debit'])) ?></dd>
                <dt>Total kredit</dt><dd><?= e(rupiah($neraca['total_kredit'])) ?></dd>
                <dt>Baris saldo tersimpan</dt><dd><?= (int) $saldoRows ?></dd>
            </dl>
            <div class="tiny muted mt-8">
                <?php if ($saldoRows === 0 && $jurnalPosted > 0): ?>
                    Cache saldo belum dibuat — klik <strong>Sinkronkan Saldo</strong>.
                <?php else: ?>
                    Cache saldo tersinkron dengan jurnal posted.
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Periode <?= (int) $tahun ?></h2>
            <div class="card-sub">Tutup buku bulanan hanya dapat dilakukan bila tidak ada jurnal yang belum posted.</div>
        </div>
        <span class="badge <?= $semuaTertutup ? 'badge--sukses' : 'badge--warning' ?>">
            <?= $semuaTertutup ? '12 periode tertutup' : count(array_filter($periods, static fn ($p) => (string) $p['status'] === 'open')) . ' periode masih terbuka' ?>
        </span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:150px">Periode</th>
                    <th class="num" style="width:80px">Jurnal</th>
                    <th class="num" style="width:120px">Belum Posted</th>
                    <th class="num" style="width:160px">Total Posted</th>
                    <th style="width:110px">Status</th>
                    <th style="width:130px">Ditutup</th>
                    <?php if ($canManage): ?><th class="right" style="width:220px">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods as $period): ?>
                    <?php
                    $pid = (int) $period['id'];
                    $count = $counts[$pid] ?? [];
                    $total = $totals[$pid] ?? ['debit' => 0.0, 'kredit' => 0.0];
                    $belum = (int) ($count['draft'] ?? 0) + (int) ($count['submitted'] ?? 0) + (int) ($count['approved'] ?? 0);
                    $status = (string) $period['status'];
                    ?>
                    <tr>
                        <td>
                            <div class="strong small"><?= e((string) $period['nama']) ?></div>
                            <div class="tiny muted"><?= e((string) $period['kode']) ?></div>
                        </td>
                        <td class="num small"><?= array_sum($count) ?></td>
                        <td class="num small"><?= $belum > 0 ? '<span class="badge badge--warning">' . $belum . '</span>' : '<span class="muted">—</span>' ?></td>
                        <td class="num small"><?= e(rupiah($total['debit'])) ?></td>
                        <td><?= status_badge($status) ?></td>
                        <td class="tiny muted"><?= $period['closed_at'] ? e(tanggal_waktu($period['closed_at'], 'd M Y')) : '—' ?></td>
                        <?php if ($canManage): ?>
                            <td>
                                <div class="row-actions">
                                    <?php if ($status === 'open'): ?>
                                        <form method="post" action="<?= e(url('closing/' . $pid . '/close')) ?>"
                                              data-confirm="Tutup buku <?= e((string) $period['nama']) ?>? Pastikan seluruh jurnal sudah posted.">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--sm btn--dark" type="submit" <?= $belum > 0 ? 'disabled title="Masih ada jurnal belum posted"' : '' ?>>
                                                <?= icon('lock', 13) ?> Tutup
                                            </button>
                                        </form>
                                    <?php elseif ($status === 'closed'): ?>
                                        <form method="post" action="<?= e(url('closing/' . $pid . '/lock')) ?>"
                                              data-confirm="Kunci permanen <?= e((string) $period['nama']) ?>? Setelah dikunci periode tidak dapat dibuka lagi.">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--sm" type="submit"><?= icon('shield', 13) ?> Kunci</button>
                                        </form>
                                        <details class="action-pop">
                                            <summary class="btn btn--sm btn--ghost"><?= icon('refresh', 13) ?></summary>
                                            <div class="action-pop-body">
                                                <form method="post" action="<?= e(url('closing/' . $pid . '/reopen')) ?>" class="stack-8">
                                                    <?= csrf_field() ?>
                                                    <label class="field-label">Buka kembali</label>
                                                    <textarea class="textarea" name="alasan" rows="2" placeholder="Alasan (wajib)" required></textarea>
                                                    <button class="btn btn--sm" type="submit">Buka Periode</button>
                                                </form>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="tiny muted"><?= icon('shield', 13) ?> terkunci</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card card--flush">
    <div class="card-head">
        <div>
            <h2 class="card-title">Riwayat Tutup Buku</h2>
            <div class="card-sub">Jejak eksekusi tutup buku bulanan dan tahunan.</div>
        </div>
    </div>
    <?php if ($riwayat === []): ?>
        <div class="empty-state" style="padding:32px">
            <div class="icon-wrap"><?= icon('history', 20) ?></div>
            <h3>Belum ada riwayat</h3>
            <p>Riwayat akan muncul setelah tutup buku pertama dijalankan.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:110px">Waktu</th>
                        <th style="width:170px">Periode</th>
                        <th style="width:110px">Tipe</th>
                        <th class="num" style="width:150px">Laba/Rugi</th>
                        <th style="width:170px">Jurnal Penutup</th>
                        <th>Catatan</th>
                        <th style="width:140px">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $item): ?>
                        <tr>
                            <td class="small"><?= e(tanggal_waktu($item['executed_at'], 'd M Y H:i')) ?></td>
                            <td class="small"><?= e((string) ($item['periode_nama'] ?? $item['periode_kode'] ?? '—')) ?></td>
                            <td><?= badge(ucfirst((string) $item['tipe']), $item['tipe'] === 'tahunan' ? 'primary' : 'netral') ?></td>
                            <td class="num small"><?= e(rupiah($item['laba_rugi'])) ?></td>
                            <td class="small">
                                <?php if (!empty($item['journal_id'])): ?>
                                    <a class="mono" href="<?= e(url('journal/' . (int) $item['journal_id'])) ?>"><?= e((string) $item['journal_nomor']) ?></a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small muted"><?= e((string) ($item['catatan'] ?? '—')) ?></td>
                            <td class="small"><?= e((string) ($item['user_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
