<?php
/**
 * Modul yang dijadwalkan pada tahap berikutnya.
 *
 * @var array $module
 * @var array $stage
 */
?>
<div class="grid-main">
    <div class="card">
        <div class="card-head">
            <div>
                <div class="row" style="gap:8px;margin-bottom:6px">
                    <span class="badge badge--warning">Tahap <?= (int) $module['stage'] ?></span>
                    <span class="badge badge--netral"><?= e((string) $stage['title']) ?></span>
                </div>
                <h2 class="card-title" style="font-size:17px"><?= e((string) $module['title']) ?></h2>
                <div class="card-sub"><?= e((string) $module['description']) ?></div>
            </div>
        </div>
        <div class="card-body">
            <div class="notice notice--info mb-16">
                <?= icon('info', 16) ?>
                <div>
                    <strong>Modul ini sedang dalam antrean pengembangan.</strong>
                    Struktur database, hak akses, penomoran, dan approval engine sudah disiapkan pada Tahap 1,
                    sehingga modul dapat dibangun tanpa perubahan skema besar.
                    Tema tahap: <?= e((string) $stage['theme']) ?>.
                </div>
            </div>

            <div class="summary-label" style="margin-bottom:10px">Ruang lingkup yang akan dibangun</div>
            <div class="stack-8">
                <?php foreach ($module['features'] as $feature): ?>
                    <div class="row" style="gap:9px;align-items:flex-start">
                        <span style="color:var(--success);margin-top:1px"><?= icon('check', 15) ?></span>
                        <span class="small"><?= e((string) $feature) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <a class="btn btn--primary" href="<?= e(url('dashboard')) ?>"><?= icon('dashboard', 15) ?> Kembali ke Dashboard</a>
                <a class="btn" href="<?= e(url('master/coa')) ?>"><?= icon('list', 15) ?> Lihat Master Data</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Status Roadmap</h2>
                <div class="card-sub">Mengacu pada projects.md</div>
            </div>
        </div>
        <div class="card-body">
            <div class="feed">
                <?php
                $roadmap = [
                    1 => 'Fondasi, Master Data & Dashboard',
                    2 => 'Akuntansi Inti',
                    3 => 'Kas & Bank, Uang Muka, Biaya',
                    4 => 'Hutang & Piutang',
                    5 => 'Anggaran & Approval Center',
                    6 => 'Aset Tetap & Perpajakan',
                    7 => 'Pelaporan Keuangan & PDF',
                    8 => 'Integrasi & Hardening',
                ];
                ?>
                <?php foreach ($roadmap as $step => $title): ?>
                    <?php $isNow = $step === (int) $module['stage']; $isDone = $step < (int) $module['stage']; ?>
                    <div class="feed-item">
                        <span class="feed-icon <?= $isDone ? 'feed-icon--success' : ($isNow ? 'feed-icon--warning' : '') ?>">
                            <?= $isDone ? icon('check', 15) : ($isNow ? icon('clock', 15) : icon('lock', 14)) ?>
                        </span>
                        <div class="feed-body">
                            <div class="feed-title">Tahap <?= (int) $step ?> — <?= e($title) ?></div>
                            <div class="feed-meta">
                                <?= $isDone ? 'Selesai' : ($isNow ? 'Sedang dikerjakan' : 'Menunggu antrean') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
