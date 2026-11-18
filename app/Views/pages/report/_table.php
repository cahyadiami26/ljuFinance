<?php
/**
 * Tabel laporan keuangan generik.
 *
 * @var array $laporan
 */
use Lju\Services\ReportService;

$kolom = $laporan['kolom'];
$jumlahKolom = count($kolom);
$isPersen = [];
foreach ($kolom as $i => $k) {
    $isPersen[$i] = str_contains((string) $k['label'], '%') || str_contains((string) $k['label'], 'Margin');
}
?>
<div class="rpt-sheet">
    <div class="rpt-head">
        <div>
            <div class="rpt-company"><?= e((string) ($laporan['entitas']['name'] ?? setting('company_name', ''))) ?></div>
            <div class="rpt-company-meta">
                <?= e((string) ($laporan['entitas']['address'] ?? '')) ?>
                <?php if (!empty($laporan['entitas']['npwp'])): ?>
                    · NPWP <?= e((string) $laporan['entitas']['npwp']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="rpt-head-right">
            <span class="badge badge--netral"><?= e((string) setting('currency', 'IDR')) ?></span>
            <span class="badge badge--<?= (string) ($laporan['periode']['status'] ?? 'open') === 'open' ? 'sukses' : 'netral' ?>">
                <?= e(ucfirst((string) ($laporan['periode']['status'] ?? 'open'))) ?>
            </span>
        </div>
    </div>

    <div class="rpt-title-block">
        <h2 class="rpt-title"><?= e((string) $laporan['judul']) ?></h2>
        <div class="rpt-subtitle"><?= e((string) $laporan['rentang']['label']) ?></div>
        <div class="rpt-meta">
            <span><strong>Periode</strong> <?= e((string) ($laporan['periode']['nama'] ?? '-')) ?></span>
            <span><strong>Pembanding</strong> <?= e((string) ($laporan['rentang']['prev_label'] ?? '-')) ?></span>
            <span><strong>Sumber</strong> Jurnal posted</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="rpt-table">
            <thead>
                <tr>
                    <?php foreach ($kolom as $i => $k): ?>
                        <th class="<?= ($k['align'] ?? 'left') === 'right' ? 'num' : '' ?>" style="width:<?= (float) ($k['lebar'] ?? 0) ?>%">
                            <?= e((string) $k['label']) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($laporan['baris'] as $row): ?>
                    <?php $tipe = (string) $row['tipe']; ?>
                    <?php if ($tipe === 'spacer'): ?>
                        <tr class="rpt-spacer"><td colspan="<?= $jumlahKolom ?>">&nbsp;</td></tr>
                    <?php elseif ($tipe === 'section'): ?>
                        <tr class="rpt-section">
                            <td colspan="<?= $jumlahKolom ?>"><?= e((string) $row['label']) ?></td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $class = match ($tipe) {
                            'total'    => 'rpt-total',
                            'subtotal' => 'rpt-subtotal',
                            default    => 'rpt-data',
                        };
                        $level = (int) ($row['level'] ?? 0);
                        ?>
                        <tr class="<?= $class ?>">
                            <td>
                                <?php if (!empty($row['kode'])): ?>
                                    <span class="rpt-kode"><?= e((string) $row['kode']) ?></span>
                                <?php endif; ?>
                                <span class="<?= $level > 0 ? 'rpt-indent' : '' ?>"><?= e((string) $row['label']) ?></span>
                                <?php if (!empty($row['catatan'])): ?>
                                    <span class="rpt-catatan"><?= e((string) $row['catatan']) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php foreach (array_values((array) $row['nilai']) as $index => $nilai): ?>
                                <?php $kolomIndex = $index + 1; ?>
                                <td class="num">
                                    <?php
                                    if ($isPersen[$kolomIndex] ?? false) {
                                        echo $nilai === null ? '<span class="muted">—</span>' : e(number_format((float) $nilai, 2, ',', '.') . '%');
                                    } else {
                                        echo $nilai === null ? '<span class="muted">—</span>' : e(ReportService::uang($nilai));
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <?php for ($i = count($row['nilai']) + 1; $i < $jumlahKolom; $i++): ?>
                                <td class="num">
                                    <?php if ($i === $jumlahKolom - 1 && $row['persen'] !== null): ?>
                                        <span class="trend <?= (float) $row['persen'] >= 0 ? 'trend--up' : 'trend--down' ?>">
                                            <?= e(ReportService::pctLabel((float) $row['persen'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($laporan['catatan'])): ?>
        <div class="rpt-notes">
            <?php foreach ((array) $laporan['catatan'] as $note): ?>
                <div class="rpt-note"><?= icon('info', 13) ?> <span><?= e((string) $note) ?></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
