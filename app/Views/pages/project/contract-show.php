<?php
/**
 * Detail kontrak dan termin penagihan.
 *
 * @var array $contract
 * @var array $terms
 * @var array $recognitions
 * @var array $statusList
 * @var array $termList
 * @var bool $canManage
 */
$nilai = (float) $contract['nilai_kontrak'];
$totalTermin = array_sum(array_map(static fn ($t) => (float) $t['nilai'], $terms));
$ditagih = 0.0;
$tertagih = 0;
foreach ($terms as $term) {
    if ((string) $term['status'] !== 'belum') {
        $ditagih += (float) $term['nilai'];
        $tertagih++;
    }
}
$ppn = round($nilai * (float) $contract['ppn_persen'] / 100, 2);
$aktif = in_array((string) $contract['status'], ['aktif', 'selesai'], true);
$badge = static fn (string $s): string => match ($s) {
    'aktif', 'selesai', 'ditagih', 'dibayar' => 'sukses',
    'batal', 'void' => 'bahaya',
    'diajukan' => 'warning',
    default => 'netral',
};
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Nilai Kontrak</div>
        <div class="summary-value"><?= e(rupiah_short($nilai)) ?></div>
        <div class="tiny muted">PPN <?= e(number_format((float) $contract['ppn_persen'], 2, ',', '.')) ?>% · <?= e(rupiah_short($ppn)) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Termin</div>
        <div class="summary-value"><?= count($terms) ?></div>
        <div class="tiny muted"><?= $tertagih ?> sudah ditagih</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Nilai Ditagih</div>
        <div class="summary-value"><?= e(rupiah_short($ditagih)) ?></div>
        <div class="tiny muted">Dasar invoice AR</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sisa Belum Ditagih</div>
        <div class="summary-value summary-value--up"><?= e(rupiah_short(max(0, $nilai - $ditagih))) ?></div>
        <div class="tiny muted">Termin berstatus belum</div>
    </div>
</div>

<div class="grid-main mb-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi Kontrak</h2>
                <div class="card-sub"><?= e((string) $contract['judul']) ?></div>
            </div>
            <span class="badge badge--<?= $badge((string) $contract['status']) ?>"><?= e($statusList[(string) $contract['status']] ?? (string) $contract['status']) ?></span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <div class="field-label">Proyek</div>
                    <div class="small strong">
                        <?php if (!empty($contract['project_id'])): ?>
                            <a href="<?= e(url('project/' . (int) $contract['project_id'])) ?>"><?= e((string) ($contract['project_kode'] ?? '') . ' — ' . (string) ($contract['project_nama'] ?? '')) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
                <div class="field">
                    <div class="field-label">Pemberi Kerja</div>
                    <div class="small"><?= e((string) ($contract['partner_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Tanggal Kontrak</div>
                    <div class="small"><?= e($contract['tanggal'] ? date('d M Y', strtotime((string) $contract['tanggal'])) : '—') ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Masa Berlaku</div>
                    <div class="small">
                        <?= e($contract['tanggal_mulai'] ? date('d M Y', strtotime((string) $contract['tanggal_mulai'])) : '—') ?>
                        s.d.
                        <?= e($contract['tanggal_akhir'] ? date('d M Y', strtotime((string) $contract['tanggal_akhir'])) : '—') ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($contract['keterangan'])): ?>
                <div class="notice notice--info mt-12">
                    <?= icon('info', 15) ?>
                    <div class="tiny"><?= e((string) $contract['keterangan']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Aksi Kontrak</h2>
                <div class="card-sub">Ubah status kontrak sesuai progres pekerjaan</div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$canManage): ?>
                <p class="muted small">Anda tidak memiliki hak untuk mengubah status kontrak.</p>
            <?php else: ?>
                <form method="post" action="<?= e(url('project/contract/' . (int) $contract['id'] . '/status')) ?>" class="mb-12">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="aktif">
                    <button class="btn btn--primary btn--block" type="submit" <?= (string) $contract['status'] === 'aktif' ? 'disabled' : '' ?>>
                        <?= icon('check', 15) ?> Aktifkan Kontrak
                    </button>
                </form>
                <form method="post" action="<?= e(url('project/contract/' . (int) $contract['id'] . '/status')) ?>" class="mb-12">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="selesai">
                    <button class="btn btn--block" type="submit" <?= (string) $contract['status'] === 'selesai' ? 'disabled' : '' ?>>
                        <?= icon('check-circle', 15) ?> Tandai Selesai
                    </button>
                </form>
                <form method="post" action="<?= e(url('project/contract/' . (int) $contract['id'] . '/status')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="batal">
                    <button class="btn btn--danger btn--block" type="submit" <?= (string) $contract['status'] === 'batal' ? 'disabled' : '' ?>>
                        <?= icon('x', 15) ?> Batalkan Kontrak
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Termin Penagihan</h2>
            <div class="card-sub">Setiap termin dapat ditagih satu kali menjadi draft invoice AR</div>
        </div>
        <span class="pill mono"><?= e(rupiah($totalTermin)) ?> / <?= e(rupiah($nilai)) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Deskripsi</th>
                    <th class="num" style="width:90px">%</th>
                    <th class="num" style="width:150px">Nilai</th>
                    <th style="width:130px">Status</th>
                    <th style="width:180px">Invoice AR</th>
                    <th style="width:150px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($terms as $term): ?>
                    <?php $status = (string) $term['status']; ?>
                    <tr>
                        <td class="small strong"><?= (int) $term['termin_ke'] ?></td>
                        <td class="small"><?= e((string) $term['deskripsi']) ?></td>
                        <td class="num small"><?= e(number_format((float) $term['persentase'], 2, ',', '.')) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $term['nilai'])) ?></td>
                        <td><span class="badge badge--<?= $badge($status) ?>"><?= e($termList[$status] ?? $status) ?></span></td>
                        <td class="small">
                            <?php if (!empty($term['ar_id'])): ?>
                                <a class="mono" href="<?= e(url('ar/' . (int) $term['ar_id'])) ?>"><?= e((string) $term['ar_nomor']) ?></a>
                                <div class="tiny muted"><?= e(ucfirst((string) $term['ar_status'])) ?></div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canManage && $status === 'belum' && $aktif && empty($term['ar_id'])): ?>
                                <form method="post" action="<?= e(url('project/contract/' . (int) $contract['id'] . '/tagih')) ?>" data-confirm="Buat draft invoice AR untuk termin ini?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="term_id" value="<?= (int) $term['id'] ?>">
                                    <button class="btn btn--sm" type="submit">
                                        <?= icon('receipt', 14) ?> Tagih Termin
                                    </button>
                                </form>
                            <?php elseif (!$aktif): ?>
                                <span class="tiny muted">Aktifkan kontrak dulu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($terms === []): ?>
                    <tr><td colspan="7"><p class="muted small" style="padding:16px">Kontrak belum memiliki termin.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card card--flush">
    <div class="card-head">
        <div>
            <h2 class="card-title">Pengakuan Pendapatan Kontrak</h2>
            <div class="card-sub">Pendapatan posted yang merujuk kontrak ini</div>
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
                    <th class="num" style="width:140px">Nilai</th>
                    <th style="width:100px">Status</th>
                    <th style="width:150px">Jurnal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recognitions as $rec): ?>
                    <tr>
                        <td class="mono small"><?= e((string) $rec['nomor']) ?></td>
                        <td class="small"><?= e(date('d M Y', strtotime((string) $rec['tanggal']))) ?></td>
                        <td class="small"><?= e(str_replace('_', ' ', (string) $rec['metode'])) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $rec['nilai'])) ?></td>
                        <td><span class="badge badge--<?= $badge((string) $rec['status']) ?>"><?= e(ucfirst((string) $rec['status'])) ?></span></td>
                        <td class="small"><?= e((string) ($rec['journal_nomor'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recognitions === []): ?>
                    <tr><td colspan="6"><p class="muted small" style="padding:16px">Belum ada pengakuan pendapatan pada kontrak ini.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
