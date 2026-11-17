<?php
/**
 * Komitmen pengadaan (PO/kontrak) yang mengikat pagu.
 *
 * @var array $rows
 * @var string $status
 * @var string $q
 * @var int $tahun
 * @var array $tahunList
 * @var array $statusList
 * @var array $ringkasan
 * @var array $budgets
 * @var array $partners
 * @var array $coaBeban
 * @var array $projects
 * @var array $bidang
 * @var array $divisi
 * @var array $costCenters
 * @var bool $canManage
 */
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Total Komitmen <?= (int) $tahun ?></div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['total'] ?? 0))) ?></div>
        <div class="tiny muted"><?= (int) ($ringkasan['jumlah'] ?? 0) ?> PO/kontrak</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Masih Terbuka</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['terbuka'] ?? 0))) ?></div>
        <div class="tiny muted">Mengikat pagu berjalan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Sebagian</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['sebagian'] ?? 0))) ?></div>
        <div class="tiny muted">Sudah terealisasi sebagian</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Selesai / Dibatalkan</div>
        <div class="summary-value"><?= e(rupiah((float) ($ringkasan['selesai'] ?? 0) + (float) ($ringkasan['batal'] ?? 0))) ?></div>
        <div class="tiny muted">Tidak lagi mengikat pagu</div>
    </div>
</div>

<form class="toolbar mb-16" method="get" action="<?= e(url('budget/commitments')) ?>">
    <input type="hidden" name="r" value="budget/commitments">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor PO atau keterangan…">
    </div>
    <select class="filter-select" name="tahun" data-autosubmit>
        <?php foreach ($tahunList as $opsi): ?>
            <option value="<?= (int) $opsi ?>" <?= (int) $tahun === (int) $opsi ? 'selected' : '' ?>>Tahun <?= (int) $opsi ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach ($statusList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <?php if ($canManage): ?>
        <button class="btn btn--primary" type="button" data-toggle="#commitment-form"><?= icon('plus', 15) ?> Catat Komitmen</button>
    <?php endif; ?>
</form>

<?php if ($canManage): ?>
    <div class="card mb-16" id="commitment-form" hidden>
        <div class="card-head">
            <div>
                <h2 class="card-title">Catat Komitmen Pengadaan</h2>
                <div class="card-sub">Nomor PO harus unik. Nilai komitmen mengurangi pagu tersedia pada monitoring.</div>
            </div>
        </div>
        <form method="post" action="<?= e(url('budget/commitments')) ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="c-nomor">Nomor PO / Kontrak</label>
                        <input class="input" type="text" id="c-nomor" name="nomor_po" maxlength="40" required placeholder="PO-2026-0001">
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-tanggal">Tanggal</label>
                        <input class="input" type="date" id="c-tanggal" name="tanggal" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-budget">RKAP</label>
                        <select class="select" id="c-budget" name="budget_id">
                            <option value="">— tanpa RKAP —</option>
                            <?php foreach ($budgets as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"><?= e($item['nomor'] . ' · ' . $item['judul']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-partner">Vendor</label>
                        <select class="select" id="c-partner" name="partner_id">
                            <option value="">— pilih vendor —</option>
                            <?php foreach ($partners as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-coa">Akun Beban</label>
                        <select class="select" id="c-coa" name="coa_id" required>
                            <option value="">— pilih akun —</option>
                            <?php foreach ($coaBeban as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"><?= e($item['kode'] . ' — ' . $item['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-nilai">Nilai Komitmen</label>
                        <input class="input money" type="text" inputmode="numeric" data-money id="c-nilai" name="nilai" required placeholder="0">
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-divisi">Divisi</label>
                        <select class="select" id="c-divisi" name="divisi_id">
                            <option value="">—</option>
                            <?php foreach ($divisi as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-bidang">Bidang</label>
                        <select class="select" id="c-bidang" name="bidang_id">
                            <option value="">—</option>
                            <?php foreach ($bidang as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-cc">Cost Center</label>
                        <select class="select" id="c-cc" name="cost_center_id">
                            <option value="">—</option>
                            <?php foreach ($costCenters as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="c-project">Proyek</label>
                        <select class="select" id="c-project" name="project_id">
                            <option value="">—</option>
                            <?php foreach ($projects as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['nama']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field field--full">
                        <label class="field-label" for="c-keterangan">Keterangan</label>
                        <input class="input" type="text" id="c-keterangan" name="keterangan" maxlength="255" placeholder="Uraian singkat pengadaan">
                    </div>
                </div>
            </div>
            <div class="card-foot row" style="justify-content:flex-end;gap:8px">
                <button class="btn" type="button" data-toggle="#commitment-form">Batal</button>
                <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Komitmen</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('briefcase', 22) ?></div>
            <h3>Belum ada komitmen pengadaan</h3>
            <p>Catat PO/kontrak agar pagu yang sudah terikat tercermin pada monitoring anggaran.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:160px">Nomor PO</th>
                        <th style="width:105px">Tanggal</th>
                        <th>Vendor / Keterangan</th>
                        <th style="width:200px">Akun</th>
                        <th class="num" style="width:150px">Nilai</th>
                        <th style="width:110px">Status</th>
                        <?php if ($canManage): ?><th style="width:170px">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="mono strong"><?= e((string) $row['nomor_po']) ?></div>
                                <div class="tiny muted"><?= e((string) ($row['budget_nomor'] ?? '—')) ?></div>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td>
                                <div class="small strong"><?= e((string) ($row['partner_nama'] ?? 'Tanpa vendor')) ?></div>
                                <div class="tiny muted"><?= e(str_limit((string) ($row['keterangan'] ?? '—'), 60)) ?></div>
                            </td>
                            <td class="small"><span class="mono"><?= e((string) ($row['coa_kode'] ?? '—')) ?></span> <?= e((string) ($row['coa_nama'] ?? '')) ?></td>
                            <td class="num strong"><?= e(rupiah((float) $row['nilai'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="row" style="gap:6px">
                                        <form method="post" action="<?= e(url('budget/commitments/' . (int) $row['id'] . '/status')) ?>">
                                            <?= csrf_field() ?>
                                            <select class="select select--sm" name="status" style="width:104px">
                                                <?php foreach ($statusList as $value => $label): ?>
                                                    <option value="<?= e($value) ?>" <?= (string) $row['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="icon-btn" type="submit" title="Ubah status"><?= icon('check', 14) ?></button>
                                        </form>
                                        <form method="post" action="<?= e(url('budget/commitments/' . (int) $row['id'] . '/delete')) ?>" data-confirm="Hapus komitmen <?= e((string) $row['nomor_po']) ?>?">
                                            <?= csrf_field() ?>
                                            <button class="icon-btn" type="submit" title="Hapus"><?= icon('x', 14) ?></button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
