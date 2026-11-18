<?php
/**
 * Detail purchase order.
 *
 * @var array $po
 * @var array $items
 * @var array $receipts
 * @var array $receiptItems
 * @var float $qtyTotal
 * @var float $qtyDiterima
 * @var array $statusList
 * @var bool $canManage
 * @var bool $canApprove
 */
$status = (string) $po['status'];
$persen = $qtyTotal > 0 ? min(100, $qtyDiterima / $qtyTotal * 100) : 0;
$badge = static fn (string $s): string => match ($s) {
    'disetujui', 'selesai' => 'sukses',
    'batal' => 'bahaya',
    'diajukan', 'sebagian' => 'warning',
    default => 'netral',
};
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Total PO</div>
        <div class="summary-value"><?= e(rupiah_short((float) $po['total'])) ?></div>
        <div class="tiny muted">Subtotal <?= e(rupiah_short((float) $po['subtotal'])) ?> + PPN</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Status</div>
        <div class="summary-value" style="font-size:18px">
            <span class="badge badge--<?= $badge($status) ?>"><?= e($statusList[$status] ?? $status) ?></span>
        </div>
        <div class="tiny muted"><?= e(date('d M Y', strtotime((string) $po['tanggal']))) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Penerimaan</div>
        <div class="summary-value"><?= e(number_format($persen, 0, ',', '.')) ?>%</div>
        <div class="tiny muted"><?= e(number_format($qtyDiterima, 2, ',', '.')) ?> / <?= e(number_format($qtyTotal, 2, ',', '.')) ?> qty</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Tagihan AP</div>
        <div class="summary-value" style="font-size:17px">
            <?php if (!empty($po['ap_invoice_id'])): ?>
                <a class="mono" href="<?= e(url('ap/' . (int) $po['ap_invoice_id'])) ?>"><?= e((string) ($po['ap_nomor'] ?? 'Lihat')) ?></a>
            <?php else: ?>—<?php endif; ?>
        </div>
        <div class="tiny muted"><?= !empty($po['ap_invoice_id']) ? 'Draft tagihan vendor' : 'Belum ditarik' ?></div>
    </div>
</div>

<div class="grid-main mb-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Informasi PO</h2>
                <div class="card-sub"><?= e((string) $po['partner_nama']) ?></div>
            </div>
            <span class="pill mono"><?= e((string) $po['nomor']) ?></span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <div class="field-label">Vendor</div>
                    <div class="small strong"><?= e((string) $po['partner_kode'] . ' — ' . $po['partner_nama']) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Proyek</div>
                    <div class="small"><?= e((string) ($po['project_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Unit Usaha</div>
                    <div class="small"><?= e((string) ($po['business_unit_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Bidang / Divisi</div>
                    <div class="small"><?= e((string) ($po['bidang_nama'] ?? '—')) ?> / <?= e((string) ($po['divisi_nama'] ?? '—')) ?></div>
                </div>
                <div class="field">
                    <div class="field-label">Termin Pembayaran</div>
                    <div class="small"><?= (int) $po['termin_hari'] ?> hari</div>
                </div>
                <div class="field">
                    <div class="field-label">Dibuat oleh</div>
                    <div class="small"><?= e((string) ($po['pembuat'] ?? '—')) ?> · <?= e(date('d M Y H:i', strtotime((string) $po['created_at']))) ?></div>
                </div>
            </div>
            <?php if (!empty($po['catatan'])): ?>
                <div class="notice notice--info mt-12">
                    <?= icon('info', 15) ?>
                    <div class="tiny"><?= e((string) $po['catatan']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Alur Persetujuan</h2>
                <div class="card-sub">Setiap aksi tercatat pada audit log</div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($status === 'draft' && $canManage): ?>
                <form method="post" action="<?= e(url('procurement/' . (int) $po['id'] . '/ajukan')) ?>" class="mb-8">
                    <?= csrf_field() ?>
                    <button class="btn btn--primary btn--block" type="submit"><?= icon('arrow-up', 15) ?> Ajukan Persetujuan</button>
                </form>
                <form method="post" action="<?= e(url('procurement/' . (int) $po['id'] . '/hapus')) ?>" data-confirm="Hapus draft PO ini?">
                    <?= csrf_field() ?>
                    <button class="btn btn--danger btn--block" type="submit"><?= icon('x', 15) ?> Hapus Draft</button>
                </form>
            <?php elseif ($status === 'diajukan' && $canApprove): ?>
                <form method="post" action="<?= e(url('procurement/' . (int) $po['id'] . '/setujui')) ?>" class="mb-8">
                    <?= csrf_field() ?>
                    <button class="btn btn--primary btn--block" type="submit"><?= icon('check', 15) ?> Setujui PO</button>
                </form>
                <form method="post" action="<?= e(url('procurement/' . (int) $po['id'] . '/batal')) ?>" data-confirm="Batalkan PO ini?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="alasan" value="Dibatalkan pada alur persetujuan">
                    <button class="btn btn--danger btn--block" type="submit"><?= icon('x', 15) ?> Batalkan</button>
                </form>
            <?php elseif (in_array($status, ['disetujui', 'sebagian'], true)): ?>
                <?php if ($canManage): ?>
                    <a class="btn btn--primary btn--block mb-8" href="<?= e(url('procurement/' . (int) $po['id'] . '/terima')) ?>"><?= icon('truck', 15) ?> Terima Barang</a>
                    <?php if ($qtyDiterima > 0 && empty($po['ap_invoice_id'])): ?>
                        <form method="post" action="<?= e(url('procurement/' . (int) $po['id'] . '/tagih')) ?>" data-confirm="Buat draft tagihan AP dari penerimaan barang?">
                            <?= csrf_field() ?>
                            <button class="btn btn--block" type="submit"><?= icon('receipt', 15) ?> Tarik Tagihan AP</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted small">Hanya pengadaan dengan hak kelola yang dapat menerima barang.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted small">Tidak ada aksi tersedia untuk status ini.</p>
            <?php endif; ?>

            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">PO disetujui otomatis menjadi komitmen anggaran. Nilai komitmen dihitung dari subtotal sebelum PPN.</div>
            </div>
        </div>
    </div>
</div>

<div class="card card--flush mb-16">
    <div class="card-head">
        <div>
            <h2 class="card-title">Rincian Barang / Jasa</h2>
            <div class="card-sub">Qty diterima diakumulasi dari seluruh penerimaan</div>
        </div>
        <span class="pill"><?= count($items) ?> baris</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th style="width:140px">Akun</th>
                    <th class="num" style="width:90px">Qty</th>
                    <th style="width:80px">Satuan</th>
                    <th class="num" style="width:130px">Harga</th>
                    <th class="num" style="width:80px">PPN %</th>
                    <th class="num" style="width:130px">Jumlah</th>
                    <th class="num" style="width:110px">Diterima</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="small">
                            <?= e((string) $item['deskripsi']) ?>
                            <?php if (!empty($item['item_kode'])): ?>
                                <div class="tiny muted"><?= e((string) $item['item_kode']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small mono"><?= e((string) ($item['coa_kode'] ?? '—')) ?></td>
                        <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',')) ?></td>
                        <td class="small"><?= e((string) $item['satuan']) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $item['harga'])) ?></td>
                        <td class="num small"><?= e(number_format((float) $item['ppn_persen'], 2, ',', '.')) ?></td>
                        <td class="num mono"><?= e(rupiah((float) $item['total'])) ?></td>
                        <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $item['diterima_qty'], 2, ',', '.'), '0'), ',')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($items === []): ?>
                    <tr><td colspan="8"><p class="muted small" style="padding:16px">PO tidak memiliki rincian.</p></td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="num strong">Subtotal</td>
                    <td class="num mono strong"><?= e(rupiah((float) $po['subtotal'])) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="6" class="num strong">PPN</td>
                    <td class="num mono strong"><?= e(rupiah((float) $po['ppn'])) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="6" class="num strong">Total</td>
                    <td class="num mono strong"><?= e(rupiah((float) $po['total'])) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card card--flush">
    <div class="card-head">
        <div>
            <h2 class="card-title">Penerimaan Barang</h2>
            <div class="card-sub">Setiap penerimaan membentuk GR dan mutasi stok</div>
        </div>
        <span class="pill"><?= count($receipts) ?> GR</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:150px">Nomor GR</th>
                    <th style="width:105px">Tanggal</th>
                    <th>Gudang</th>
                    <th>Rincian</th>
                    <th style="width:140px">Diterima oleh</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $receipt): ?>
                    <tr>
                        <td class="mono small"><?= e((string) $receipt['nomor']) ?></td>
                        <td class="small"><?= e(date('d M Y', strtotime((string) $receipt['tanggal']))) ?></td>
                        <td class="small"><?= e((string) ($receipt['warehouse_nama'] ?? '—')) ?></td>
                        <td class="small">
                            <?php foreach ((array) ($receiptItems[(int) $receipt['id']] ?? []) as $baris): ?>
                                <div><?= e((string) $baris['item_kode']) ?> · <?= e(rtrim(rtrim(number_format((float) $baris['qty'], 2, ',', '.'), '0'), ',')) ?> <?= e((string) $baris['satuan']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td class="small"><?= e((string) ($receipt['pembuat'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($receipts === []): ?>
                    <tr><td colspan="5"><p class="muted small" style="padding:16px">Belum ada penerimaan barang untuk PO ini.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
