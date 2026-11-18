<?php
/**
 * Tampilan cetak laporan keuangan.
 *
 * @var array $laporan
 * @var array $entitas
 * @var array $tandaTangan
 */
use Lju\Core\View;

$periode = $laporan['periode'] ?? [];
$catatan = (array) ($laporan['catatan'] ?? []);
?>
<article class="print-document">
    <header class="print-head">
        <div>
            <div class="print-company"><?= e((string) $entitas['name']) ?></div>
            <?php if ($entitas['address'] !== ''): ?>
                <div class="print-address"><?= e((string) $entitas['address']) ?></div>
            <?php endif; ?>
            <div class="print-address">
                <?php if ($entitas['phone'] !== ''): ?>Telp. <?= e((string) $entitas['phone']) ?><?php endif; ?>
                <?php if ($entitas['email'] !== ''): ?> · <?= e((string) $entitas['email']) ?><?php endif; ?>
                <?php if ($entitas['npwp'] !== ''): ?> · NPWP <?= e((string) $entitas['npwp']) ?><?php endif; ?>
            </div>
        </div>
        <div class="print-doc-meta">
            <div class="print-doc-label">Laporan Keuangan</div>
            <div class="print-doc-date">Dicetak <?= e(tanggal(date('Y-m-d'), 'd M Y')) ?></div>
        </div>
    </header>

    <hr class="print-rule">

    <h1 class="print-title"><?= e((string) $laporan['judul']) ?></h1>
    <div class="print-subtitle">
        Periode <?= e((string) ($periode['nama'] ?? '-')) ?>
        · <?= e((string) $laporan['rentang']['label']) ?>
        · Mata uang <?= e((string) setting('currency', 'IDR')) ?>
    </div>

    <div class="print-table-wrap">
        <table class="print-table">
            <thead>
                <tr>
                    <?php foreach ($laporan['kolom'] as $k): ?>
                        <th class="<?= ($k['align'] ?? 'left') === 'right' ? 'num' : '' ?>" style="width:<?= (float) ($k['lebar'] ?? 0) ?>%"><?= e((string) $k['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($laporan['baris'] as $row): ?>
                    <?php $tipe = (string) $row['tipe']; ?>
                    <?php if ($tipe === 'spacer'): ?>
                        <tr class="print-spacer"><td colspan="<?= count($laporan['kolom']) ?>"></td></tr>
                    <?php elseif ($tipe === 'section'): ?>
                        <tr class="print-section"><td colspan="<?= count($laporan['kolom']) ?>"><?= e((string) $row['label']) ?></td></tr>
                    <?php else: ?>
                        <tr class="print-<?= e($tipe) ?>">
                            <td>
                                <?php if (!empty($row['kode'])): ?><span class="print-kode"><?= e((string) $row['kode']) ?></span><?php endif; ?>
                                <?= e((string) $row['label']) ?>
                            </td>
                            <?php foreach (array_values((array) $row['nilai']) as $index => $nilai): ?>
                                <?php $k = $laporan['kolom'][$index + 1] ?? ['label' => '']; ?>
                                <td class="num">
                                    <?php if (str_contains((string) $k['label'], '%') || str_contains((string) $k['label'], 'Margin')): ?>
                                        <?= $nilai === null ? '—' : e(number_format((float) $nilai, 2, ',', '.') . '%') ?>
                                    <?php else: ?>
                                        <?= $nilai === null ? '—' : e(\Lju\Services\ReportService::uang($nilai)) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <?php for ($i = count($row['nilai']) + 1; $i < count($laporan['kolom']); $i++): ?>
                                <td class="num muted"><?php if ($i === count($laporan['kolom']) - 1 && $row['persen'] !== null): ?><?= e(\Lju\Services\ReportService::pctLabel((float) $row['persen'])) ?><?php else: ?>—<?php endif; ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($catatan !== []): ?>
        <section class="print-notes">
            <h2>Catatan atas Laporan Keuangan</h2>
            <ol>
                <?php foreach ($catatan as $note): ?>
                    <li><?= e((string) $note) ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <section class="print-sign">
        <?php foreach ($tandaTangan as $item): ?>
            <div class="print-sign-col">
                <div class="print-sign-label"><?= e((string) $item['label']) ?></div>
                <div class="print-sign-space"></div>
                <div class="print-sign-line"></div>
                <div class="print-sign-name"><?= e($item['name'] !== '' ? (string) $item['name'] : '(..............................)') ?></div>
                <div class="print-sign-title"><?= e((string) $item['title']) ?></div>
            </div>
        <?php endforeach; ?>
    </section>

    <footer class="print-foot">
        Dokumen ini dihasilkan otomatis oleh <?= e((string) setting('app_name', 'LJU Finance')) ?> pada
        <?= e(tanggal(date('Y-m-d'), 'd M Y')) ?> pukul <?= e(date('H:i')) ?> WIB.
        Sumber: jurnal posted pada periode <?= e((string) ($periode['nama'] ?? '-')) ?>.
    </footer>
</article>
