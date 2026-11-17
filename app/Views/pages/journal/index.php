<?php
/**
 * Daftar jurnal.
 *
 * @var array $rows
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $status
 * @var string $tipe
 * @var int $periodeId
 * @var array $periods
 * @var array $summary
 * @var array $nilai
 * @var bool $canCreate
 */
use Lju\Services\JournalService;

$statusList = JournalService::STATUS;
$tipeList = JournalService::TIPE;
?>
<div class="summary-strip" style="grid-template-columns:repeat(5,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Draft</div>
        <div class="summary-value"><?= (int) ($summary['draft'] ?? 0) ?></div>
        <div class="tiny muted">Belum diajukan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Menunggu Persetujuan</div>
        <div class="summary-value"><?= (int) (($summary['submitted'] ?? 0) + ($summary['approved'] ?? 0)) ?></div>
        <div class="tiny muted"><?= (int) ($summary['submitted'] ?? 0) ?> diajukan · <?= (int) ($summary['approved'] ?? 0) ?> disetujui</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Posted</div>
        <div class="summary-value"><?= (int) ($summary['posted'] ?? 0) ?></div>
        <div class="tiny muted"><?= e(rupiah_short((float) ($nilai['posted'] ?? 0))) ?> tahun ini</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Ditolak</div>
        <div class="summary-value"><?= (int) ($summary['rejected'] ?? 0) ?></div>
        <div class="tiny muted">Perlu perbaikan</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Dibalik</div>
        <div class="summary-value"><?= (int) ($summary['reversed'] ?? 0) ?></div>
        <div class="tiny muted">Ada jurnal pembalik</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('journal')) ?>">
    <input type="hidden" name="r" value="journal">
    <div class="search-box">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor, deskripsi, referensi…">
    </div>
    <select class="filter-select" name="status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach ($statusList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="tipe" data-autosubmit>
        <option value="">Semua tipe</option>
        <?php foreach ($tipeList as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $tipe === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="periode" data-autosubmit>
        <option value="">Semua periode</option>
        <?php foreach ($periods as $period): ?>
            <option value="<?= (int) $period['id'] ?>" <?= $periodeId === (int) $period['id'] ? 'selected' : '' ?>>
                <?= e((string) $period['nama']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('filter', 15) ?> Terapkan</button>
    <span class="spacer"></span>
    <span class="pill"><?= (int) $paginator->total ?> jurnal</span>
    <?php if ($canCreate): ?>
        <a class="btn btn--primary" href="<?= e(url('journal/create')) ?>"><?= icon('plus', 15) ?> Jurnal Baru</a>
    <?php endif; ?>
</form>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('edit', 22) ?></div>
            <h3>Belum ada jurnal</h3>
            <p><?= $q !== '' || $status !== '' || $tipe !== '' || $periodeId > 0 ? 'Tidak ada jurnal yang cocok dengan filter.' : 'Mulai dengan membuat jurnal pertama pada periode berjalan.' ?></p>
            <?php if ($canCreate): ?>
                <a class="btn btn--primary mt-16" href="<?= e(url('journal/create')) ?>"><?= icon('plus', 15) ?> Jurnal Baru</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px">Nomor</th>
                        <th style="width:100px">Tanggal</th>
                        <th>Deskripsi</th>
                        <th style="width:150px">Tipe</th>
                        <th class="num" style="width:150px">Total</th>
                        <th style="width:110px">Status</th>
                        <th class="right" style="width:80px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a class="strong" style="font-size:12.5px" href="<?= e(url('journal/' . (int) $row['id'])) ?>"><?= e((string) $row['nomor']) ?></a>
                                <?php if (!empty($row['reversed_by_id'])): ?>
                                    <div class="tiny muted">dibalik</div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e(tanggal($row['tanggal'], 'd M Y')) ?></td>
                            <td>
                                <div class="small"><?= e(str_limit((string) $row['deskripsi'], 70)) ?></div>
                                <div class="tiny muted">
                                    <?= e((string) ($row['periode_nama'] ?? '')) ?>
                                    <?php if (!empty($row['referensi'])): ?> · <span class="code-pill"><?= e((string) $row['referensi']) ?></span><?php endif; ?>
                                    <?php if (!empty($row['created_by_name'])): ?> · <?= e((string) $row['created_by_name']) ?><?php endif; ?>
                                </div>
                            </td>
                            <td class="small muted"><?= e(JournalService::TIPE[(string) $row['tipe']] ?? (string) $row['tipe']) ?></td>
                            <td class="num strong"><?= e(rupiah($row['total_debit'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn--sm" href="<?= e(url('journal/' . (int) $row['id'])) ?>"><?= icon('eye', 14) ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> jurnal</span>
            <?= $paginator->links('journal', array_filter(['q' => $q, 'status' => $status, 'tipe' => $tipe, 'periode' => $periodeId ?: null])) ?>
        </div>
    <?php endif; ?>
</div>
