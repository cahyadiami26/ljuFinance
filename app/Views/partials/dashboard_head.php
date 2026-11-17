<?php
/**
 * Header dashboard eksekutif.
 *
 * @var array  $data
 * @var string $greeting
 * @var string $roleLabel
 */
$periode = $data['periode'] ?? periode_aktif();
$statusPeriode = (string) ($periode['status'] ?? 'open');
$sinkron = $data['synced_at'] ?? null;
?>
<div class="page-head">
    <div>
        <div class="row wrap" style="gap:10px">
            <h1 class="page-title"><?= e($greeting ?? 'Selamat Pagi') ?>, <?= e(auth_user()['name'] ?? 'Pengguna') ?></h1>
            <span class="badge badge--info"><?= e($roleLabel ?? 'Pengguna') ?></span>
        </div>
        <div class="page-sub" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:7px">
            <span>Periode Fiskal: <strong class="strong"><?= e(periode_label($periode)) ?></strong></span>
            <span class="row" style="gap:6px">
                <?= icon('book', 14) ?>
                <span>Buku <?= $statusPeriode === 'open' ? 'Terbuka' : 'Tertutup' ?>
                    <span class="badge badge--<?= $statusPeriode === 'open' ? 'sukses' : 'netral' ?>"><?= $statusPeriode === 'open' ? 'Open Ledger' : ucfirst($statusPeriode) ?></span>
                </span>
            </span>
            <span>Sinkronisasi SSO terakhir:
                <strong class="strong"><?= $sinkron ? e(tanggal_waktu($sinkron)) . ' WIB' : 'hari ini' ?></strong>
            </span>
        </div>
    </div>

    <div class="page-actions">
        <a class="btn" href="<?= e(url('dashboard/refresh')) ?>"><?= icon('refresh', 16) ?> Segarkan Data</a>
        <a class="btn" href="<?= e(url('report')) ?>"><?= icon('download', 16) ?> Unduh PDF / XLSX</a>
        <a class="btn btn--dark" href="<?= e(url('approval')) ?>">
            <?= icon('check-circle', 16) ?> Batch Approval
            <?php if (($data['approval_count'] ?? 0) > 0): ?>
                <span class="nav-badge" style="margin-left:2px"><?= (int) $data['approval_count'] ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>
