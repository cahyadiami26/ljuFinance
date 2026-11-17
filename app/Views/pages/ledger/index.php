<?php
/**
 * Buku besar per akun.
 *
 * @var array $akunPerTipe
 * @var array|null $akun
 * @var int $coaId
 * @var string $from
 * @var string $to
 * @var array $filter
 * @var array $rows
 * @var float $saldoAwal
 * @var float $saldoAkhir
 * @var float $totalDebit
 * @var float $totalKredit
 * @var array $rekapBidang
 * @var array $rekapDivisi
 * @var array $rekapProyek
 * @var array $bidangList
 * @var array $divisiList
 * @var array $projectList
 * @var array $ccList
 * @var array $partnerList
 */
$tipeLabels = [
    'aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'ekuitas' => 'Ekuitas',
    'pendapatan' => 'Pendapatan', 'beban' => 'Beban',
];
$dk = static function (float $value): string {
    if (abs($value) < 0.005) {
        return '<span class="muted">—</span>';
    }
    $tag = $value > 0 ? 'D' : 'K';
    return '<span class="num-strong">' . e(rupiah(abs($value))) . '</span> <span class="dk-tag">' . $tag . '</span>';
};
$adaDimensi = $rekapBidang !== [] || $rekapDivisi !== [] || $rekapProyek !== [];
?>
<form class="card mb-16" method="get" action="<?= e(url('ledger')) ?>">
    <input type="hidden" name="r" value="ledger">
    <div class="card-body">
        <div class="form-grid" style="grid-template-columns:minmax(240px,2fr) 150px 150px auto">
            <div class="field">
                <label class="field-label" for="l-coa">Akun</label>
                <select class="select" id="l-coa" name="coa">
                    <option value="">— pilih akun —</option>
                    <?php foreach ($akunPerTipe as $tipe => $items): ?>
                        <optgroup label="<?= e($tipeLabels[$tipe] ?? ucfirst((string) $tipe)) ?>">
                            <?php foreach ($items as $item): ?>
                                <option value="<?= (int) $item['id'] ?>" <?= $coaId === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['kode'] . ' — ' . $item['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="l-from">Dari</label>
                <input class="input" type="date" id="l-from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="l-to">Sampai</label>
                <input class="input" type="date" id="l-to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field" style="justify-content:flex-end">
                <label class="field-label">&nbsp;</label>
                <button class="btn btn--primary" type="submit"><?= icon('search', 15) ?> Tampilkan</button>
            </div>
        </div>

        <details class="mt-8" <?= array_filter($filter) !== [] ? 'open' : '' ?>>
            <summary class="small muted" style="cursor:pointer">Filter dimensi (buku besar pembantu)</summary>
            <div class="form-grid mt-12" style="grid-template-columns:repeat(5,minmax(0,1fr))">
                <?php
                $dimensiFields = [
                    ['bidang', 'Bidang', $bidangList, (int) ($filter['bidang_id'] ?? 0)],
                    ['divisi', 'Divisi', $divisiList, (int) ($filter['divisi_id'] ?? 0)],
                    ['project', 'Proyek', $projectList, (int) ($filter['project_id'] ?? 0)],
                    ['cost_center', 'Cost Center', $ccList, (int) ($filter['cost_center_id'] ?? 0)],
                    ['partner', 'Vendor/Customer', $partnerList, (int) ($filter['partner_id'] ?? 0)],
                ];
                foreach ($dimensiFields as [$name, $label, $options, $selected]):
                    ?>
                    <div class="field">
                        <label class="field-label"><?= e($label) ?></label>
                        <select class="select select--sm" name="<?= e($name) ?>">
                            <option value="">Semua</option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= (int) $option['id'] ?>" <?= $selected === (int) $option['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $option['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
</form>

<?php if ($akun === null): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('book', 22) ?></div>
            <h3>Pilih akun untuk melihat buku besar</h3>
            <p>Buku besar menampilkan saldo awal, mutasi, dan saldo berjalan per akun untuk rentang tanggal yang dipilih.
                Gunakan filter dimensi untuk melihat buku besar pembantu per bidang, divisi, proyek, atau vendor.</p>
        </div>
    </div>
<?php else: ?>
    <div class="summary-strip" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <div class="summary-item">
            <div class="summary-label">Saldo Awal</div>
            <div class="summary-value"><?= $dk($saldoAwal) ?></div>
            <div class="tiny muted">Sebelum <?= e(tanggal($from)) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Mutasi Debit</div>
            <div class="summary-value"><?= e(rupiah($totalDebit)) ?></div>
            <div class="tiny muted"><?= count($rows) ?> transaksi</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Mutasi Kredit</div>
            <div class="summary-value"><?= e(rupiah($totalKredit)) ?></div>
            <div class="tiny muted"><?= e(tanggal($from, 'd M')) ?> – <?= e(tanggal($to, 'd M Y')) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Saldo Akhir</div>
            <div class="summary-value"><?= $dk($saldoAkhir) ?></div>
            <div class="tiny muted">Saldo normal <?= e((string) $akun['saldo_normal'] === 'K' ? 'kredit' : 'debit') ?></div>
        </div>
    </div>

    <div class="card card--flush mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title"><?= e((string) $akun['kode']) ?> — <?= e((string) $akun['nama']) ?></h2>
                <div class="card-sub">
                    <?= e($tipeLabels[(string) $akun['tipe']] ?? ucfirst((string) $akun['tipe'])) ?>
                    · <?= e(str_replace('_', ' ', (string) $akun['kategori'])) ?>
                    <?php if (array_filter($filter) !== []): ?>
                        · filter dimensi aktif
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge badge--netral"><?= e(tanggal($from, 'd M Y')) ?> – <?= e(tanggal($to, 'd M Y')) ?></span>
        </div>

        <?php if ($rows === []): ?>
            <div class="empty-state" style="padding:36px 20px">
                <div class="icon-wrap"><?= icon('list', 20) ?></div>
                <h3>Tidak ada mutasi</h3>
                <p>Belum ada jurnal posted pada akun ini untuk rentang tanggal dan filter yang dipilih.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:96px">Tanggal</th>
                            <th style="width:150px">Nomor</th>
                            <th>Keterangan</th>
                            <th style="width:160px">Dimensi</th>
                            <th class="num" style="width:130px">Debit</th>
                            <th class="num" style="width:130px">Kredit</th>
                            <th class="num" style="width:150px">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="row-opening">
                            <td colspan="6" class="right small strong">Saldo awal per <?= e(tanggal($from)) ?></td>
                            <td class="num strong"><?= $dk($saldoAwal) ?></td>
                        </tr>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                                <td>
                                    <a class="mono small" href="<?= e(url('journal/' . (int) $row['journal_id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                </td>
                                <td>
                                    <div class="small"><?= e((string) ($row['keterangan'] ?: $row['jurnal_deskripsi'])) ?></div>
                                    <?php if (!empty($row['referensi'])): ?>
                                        <div class="tiny muted"><?= e((string) $row['referensi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="tiny muted">
                                    <?php
                                    $dimensi = array_filter([$row['bidang_nama'] ?? null, $row['divisi_nama'] ?? null, $row['project_nama'] ?? null, $row['cost_center_nama'] ?? null, $row['partner_nama'] ?? null]);
                                    echo $dimensi === [] ? '—' : e(implode(' · ', $dimensi));
                                    ?>
                                </td>
                                <td class="num"><?= (float) $row['debit'] > 0 ? e(rupiah($row['debit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="num"><?= (float) $row['kredit'] > 0 ? e(rupiah($row['kredit'])) : '<span class="muted">—</span>' ?></td>
                                <td class="num"><?= $dk((float) $row['saldo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="right strong small">TOTAL MUTASI</td>
                            <td class="num strong"><?= e(rupiah($totalDebit)) ?></td>
                            <td class="num strong"><?= e(rupiah($totalKredit)) ?></td>
                            <td class="num strong"><?= $dk($saldoAkhir) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($adaDimensi): ?>
        <div class="grid-3">
            <?php
            $rekapGroups = [
                ['Buku Besar Pembantu — Bidang', $rekapBidang],
                ['Buku Besar Pembantu — Divisi', $rekapDivisi],
                ['Buku Besar Pembantu — Proyek', $rekapProyek],
            ];
            ?>
            <?php foreach ($rekapGroups as [$judul, $data]): ?>
                <div class="card card--flush">
                    <div class="card-head">
                        <h2 class="card-title" style="font-size:12.5px"><?= e($judul) ?></h2>
                    </div>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th class="num">Debit</th>
                                    <th class="num">Kredit</th>
                                    <th class="num">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $label => $item): ?>
                                    <tr>
                                        <td class="small"><?= e((string) $label) ?></td>
                                        <td class="num small"><?= e(rupiah($item['debit'])) ?></td>
                                        <td class="num small"><?= e(rupiah($item['kredit'])) ?></td>
                                        <td class="num small strong"><?= e(rupiah($item['saldo'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
