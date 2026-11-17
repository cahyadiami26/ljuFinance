<?php
/**
 * Daftar akun Chart of Accounts.
 *
 * @var array $rows
 * @var array $summary
 * @var array $tipeList
 * @var \Lju\Core\Paginator $paginator
 * @var string $q
 * @var string $tipe
 * @var string $status
 * @var bool $canManage
 */

$tipeVariant = ['aset' => 'primary', 'kewajiban' => 'bahaya', 'ekuitas' => 'purple', 'pendapatan' => 'sukses', 'beban' => 'warning'];
?>
<div class="summary-strip" style="grid-template-columns:repeat(5,minmax(0,1fr))">
    <?php foreach ($tipeList as $key => $label): ?>
        <?php
        $row = null;
        foreach ($summary as $item) {
            if ((string) $item['tipe'] === $key) {
                $row = $item;
                break;
            }
        }
        ?>
        <div class="summary-item">
            <div class="summary-label"><?= e($label) ?></div>
            <div class="summary-value"><?= (int) ($row['jumlah'] ?? 0) ?> <span class="tiny muted">akun</span></div>
            <div class="tiny muted"><?= (int) ($row['aktif'] ?? 0) ?> aktif</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="toolbar">
    <form class="search-box" method="get" action="<?= e(url('master/coa')) ?>">
        <?= icon('search', 16) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama akun…">
        <input type="hidden" name="r" value="master/coa">
        <input type="hidden" name="tipe" value="<?= e($tipe) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
    </form>

    <form method="get" action="<?= e(url('master/coa')) ?>" class="row" style="gap:8px">
        <input type="hidden" name="r" value="master/coa">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <select class="filter-select" name="tipe" onchange="this.form.submit()">
            <option value="">Semua klasifikasi</option>
            <?php foreach ($tipeList as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $tipe === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
    </form>

    <span class="spacer"></span>
    <?php if ($canManage): ?>
        <a class="btn btn--primary" href="<?= e(url('master/coa/create')) ?>"><?= icon('plus', 16) ?> Tambah Akun</a>
    <?php endif; ?>
</div>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('list', 22) ?></div>
            <h3>Tidak ada akun ditemukan</h3>
            <p>Sesuaikan filter pencarian atau tambahkan akun baru.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:120px">Kode</th>
                        <th>Nama Akun</th>
                        <th>Klasifikasi</th>
                        <th>Saldo Normal</th>
                        <th class="center">Posting</th>
                        <th class="center">Kas/Bank</th>
                        <th>Status</th>
                        <?php if ($canManage): ?><th class="right">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $level = (int) $row['level'];
                        $isHeader = (int) $row['allow_posting'] === 0;
                        ?>
                        <tr class="coa-row <?= $isHeader ? 'coa-row--header' : '' ?>">
                            <td><span class="code-pill"><?= e((string) $row['kode']) ?></span></td>
                            <td>
                                <div class="coa-name" style="padding-left:<?= max(0, ($level - 1) * 16) ?>px">
                                    <?php if ($isHeader): ?>
                                        <?= icon('layers', 14) ?>
                                    <?php endif; ?>
                                    <span class="coa-label"><?= e((string) $row['nama']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge--<?= e($tipeVariant[(string) $row['tipe']] ?? 'netral') ?>">
                                    <?= e($tipeList[(string) $row['tipe']] ?? ucfirst((string) $row['tipe'])) ?>
                                </span>
                            </td>
                            <td><?= $row['saldo_normal'] === 'D' ? 'Debit' : 'Kredit' ?></td>
                            <td class="center">
                                <?= $isHeader ? badge('Header', 'netral') : badge('Ya', 'sukses') ?>
                            </td>
                            <td class="center">
                                <?= (int) $row['is_kas_bank'] === 1 ? badge('Kas/Bank', 'info') : '<span class="muted">—</span>' ?>
                            </td>
                            <td><?= ((int) $row['is_active'] === 1) ? badge('Aktif', 'sukses') : badge('Nonaktif', 'netral') ?></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn--sm" href="<?= e(url('master/coa/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit', 14) ?> Ubah</a>
                                        <form method="post" action="<?= e(url('master/coa/' . (int) $row['id'] . '/toggle')) ?>"
                                              data-confirm="Ubah status akun <?= e((string) $row['kode']) ?>?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--sm" type="submit"><?= icon('power', 14) ?></button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span>Menampilkan <?= (int) $paginator->from() ?>–<?= (int) $paginator->to() ?> dari <?= (int) $paginator->total ?> akun</span>
            <?= $paginator->links('master/coa', array_filter(['q' => $q, 'tipe' => $tipe, 'status' => $status])) ?>
        </div>
    <?php endif; ?>
</div>
