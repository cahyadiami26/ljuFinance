<?php
/**
 * Detail rekonsiliasi bank.
 *
 * @var array $row
 * @var array $buku
 * @var array $bank
 * @var bool $canManage
 */
$id = (int) $row['id'];
$selisih = (float) $row['selisih'];
$totalBaris = count($buku) + count($bank);
$draft = (string) $row['status'] === 'draft';
?>
<div class="card mb-16">
    <div class="card-head" style="align-items:flex-start">
        <div>
            <div class="row" style="gap:8px;margin-bottom:6px">
                <span class="badge badge--netral mono">RECON-<?= $id ?></span>
                <?= status_badge((string) $row['status']) ?>
            </div>
            <h2 class="card-title" style="font-size:16px"><?= e((string) $row['bank_nama']) ?></h2>
            <div class="card-sub">
                <?= e((string) ($row['nama_bank'] ?? '')) ?>
                <?php if (!empty($row['nomor_rekening'])): ?> · <?= e((string) $row['nomor_rekening']) ?><?php endif; ?>
                · periode <?= e((string) ($row['periode_nama'] ?? '—')) ?>
            </div>
        </div>

        <div class="row wrap" style="gap:6px;justify-content:flex-end">
            <?php if ($canManage && $draft): ?>
                <form method="post" action="<?= e(url('cash/reconciliation/' . $id . '/finish')) ?>" class="row" style="gap:6px"
                      data-confirm="Selesaikan rekonsiliasi ini?">
                    <?= csrf_field() ?>
                    <input class="input input--sm" type="text" name="catatan" maxlength="255" placeholder="Catatan selisih (jika ada)" style="width:220px">
                    <button class="btn btn--sm btn--primary" type="submit"><?= icon('check-circle', 14) ?> Selesaikan</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && $draft): ?>
                <form method="post" action="<?= e(url('cash/reconciliation/' . $id . '/delete')) ?>" data-confirm="Hapus rekonsiliasi ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" type="submit" style="color:var(--danger)"><?= icon('x', 14) ?> Hapus</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Saldo Buku</div>
        <div class="summary-value"><?= e(rupiah((float) $row['saldo_buku'])) ?></div>
        <div class="tiny muted">Jurnal posted s.d. <?= e(tanggal($row['tanggal_akhir'], 'd M Y')) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Saldo Rekening Koran</div>
        <div class="summary-value"><?= e(rupiah((float) $row['saldo_bank'])) ?></div>
        <div class="tiny muted">Input manual</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Selisih</div>
        <div class="summary-value <?= abs($selisih) > 0.005 ? 'summary-value--down' : '' ?>"><?= e(rupiah($selisih)) ?></div>
        <div class="tiny muted"><?= abs($selisih) < 0.005 ? 'Tidak ada selisih' : 'Perlu penjelasan' ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Baris Dicocokkan</div>
        <div class="summary-value"><?= (int) $row['jumlah_cocok'] ?></div>
        <div class="tiny muted">dari <?= $totalBaris ?> baris</div>
    </div>
</div>

<?php if (!empty($row['catatan'])): ?>
    <div class="notice mb-16">
        <?= icon('info', 16) ?>
        <div><?= e((string) $row['catatan']) ?></div>
    </div>
<?php endif; ?>

<?php if ($canManage && $draft): ?>
<form method="post" action="<?= e(url('cash/reconciliation/' . $id . '/match')) ?>" data-no-spinner>
    <?= csrf_field() ?>
<?php endif; ?>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Mutasi Buku Besar</h2>
                <div class="card-sub"><?= count($buku) ?> baris jurnal pada akun rekening.</div>
            </div>
        </div>
        <?php if ($buku === []): ?>
            <div class="empty-state" style="padding:32px 20px">
                <p>Tidak ada mutasi buku pada rentang tanggal ini.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($canManage && $draft): ?><th style="width:44px" class="center"></th><?php endif; ?>
                            <th style="width:110px">Tanggal</th>
                            <th style="width:160px">Referensi</th>
                            <th>Keterangan</th>
                            <th class="num" style="width:140px">Debit</th>
                            <th class="num" style="width:140px">Kredit</th>
                            <th style="width:90px" class="center">Cocok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buku as $item): ?>
                            <tr>
                                <?php if ($canManage && $draft): ?>
                                    <td class="center">
                                        <label class="checkbox"><input type="checkbox" name="matched[]" value="<?= (int) $item['id'] ?>" <?= (int) $item['is_matched'] === 1 ? 'checked' : '' ?>></label>
                                    </td>
                                <?php endif; ?>
                                <td class="small"><?= !empty($item['tanggal']) ? e(tanggal($item['tanggal'], 'd M Y')) : '—' ?></td>
                                <td class="small mono"><?= e((string) ($item['referensi'] ?? '—')) ?></td>
                                <td class="small"><?= e((string) ($item['keterangan'] ?? '')) ?></td>
                                <td class="num"><?= (float) $item['debit'] > 0 ? e(rupiah((float) $item['debit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="num"><?= (float) $item['kredit'] > 0 ? e(rupiah((float) $item['kredit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="center"><?= (int) $item['is_matched'] === 1 ? badge('Cocok', 'sukses') : badge('Belum', 'netral') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Mutasi Rekening Koran</h2>
                <div class="card-sub"><?= count($bank) ?> baris diisi manual saat membuat rekonsiliasi.</div>
            </div>
        </div>
        <?php if ($bank === []): ?>
            <div class="empty-state" style="padding:32px 20px">
                <p>Belum ada baris rekening koran. Buat rekonsiliasi baru untuk menambahkan mutasi bank.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($canManage && $draft): ?><th style="width:44px" class="center"></th><?php endif; ?>
                            <th style="width:110px">Tanggal</th>
                            <th style="width:160px">Referensi</th>
                            <th>Keterangan</th>
                            <th class="num" style="width:140px">Debit</th>
                            <th class="num" style="width:140px">Kredit</th>
                            <th style="width:90px" class="center">Cocok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bank as $item): ?>
                            <tr>
                                <?php if ($canManage && $draft): ?>
                                    <td class="center">
                                        <label class="checkbox"><input type="checkbox" name="matched[]" value="<?= (int) $item['id'] ?>" <?= (int) $item['is_matched'] === 1 ? 'checked' : '' ?>></label>
                                    </td>
                                <?php endif; ?>
                                <td class="small"><?= !empty($item['tanggal']) ? e(tanggal($item['tanggal'], 'd M Y')) : '—' ?></td>
                                <td class="small mono"><?= e((string) ($item['referensi'] ?? '—')) ?></td>
                                <td class="small"><?= e((string) ($item['keterangan'] ?? '')) ?></td>
                                <td class="num"><?= (float) $item['debit'] > 0 ? e(rupiah((float) $item['debit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="num"><?= (float) $item['kredit'] > 0 ? e(rupiah((float) $item['kredit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="center"><?= (int) $item['is_matched'] === 1 ? badge('Cocok', 'sukses') : badge('Belum', 'netral') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php if ($canManage && $draft): ?>
    <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> Simpan Penandaan</button>
        <a class="btn" href="<?= e(url('cash/reconciliation')) ?>">Kembali</a>
        <span class="spacer"></span>
        <span class="tiny muted">Centang baris buku dan baris rekening koran yang bersesuaian, lalu simpan.</span>
    </div>
</form>
<?php endif; ?>
