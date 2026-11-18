<?php
/**
 * Cadangan database.
 *
 * @var array $daftar
 * @var array $ringkasan
 * @var string $folder
 * @var int $retensi
 */
$ukuran = static function (int $byte): string {
    if ($byte >= 1048576) {
        return number_format($byte / 1048576, 2, ',', '.') . ' MB';
    }
    if ($byte >= 1024) {
        return number_format($byte / 1024, 1, ',', '.') . ' KB';
    }
    return $byte . ' B';
};
?>
<div class="summary-strip mb-16" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="summary-item">
        <div class="summary-label">Jumlah Cadangan</div>
        <div class="summary-value"><?= (int) ($ringkasan['jumlah'] ?? 0) ?></div>
        <div class="tiny muted">Retensi otomatis <?= (int) $retensi ?> hari</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Total Ukuran</div>
        <div class="summary-value"><?= e($ukuran((int) ($ringkasan['total'] ?? 0))) ?></div>
        <div class="tiny muted">Folder <?= e($folder) ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Cadangan Terakhir</div>
        <div class="summary-value" style="font-size:17px"><?= e((string) ($ringkasan['terakhir'] ?? 'Belum ada')) ?></div>
        <div class="tiny muted">Waktu server (WIB)</div>
    </div>
</div>

<div class="grid-main mb-16">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Buat Cadangan Baru</h2>
                <div class="card-sub">Struktur tabel + seluruh data, siap dipulihkan dengan mysql/mariadb.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('system/backup')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="b-catatan">Catatan (opsional)</label>
                    <input class="input" type="text" id="b-catatan" name="catatan" maxlength="120"
                           placeholder="mis. sebelum tutup buku November">
                </div>
                <button class="btn btn--primary mt-12" type="submit"><?= icon('database', 15) ?> Buat Cadangan Sekarang</button>
            </form>
            <div class="notice notice--warning mt-16">
                <?= icon('alert', 15) ?>
                <div class="tiny">
                    Proses berjalan sinkron dan memakai memori server. Untuk database besar, jalankan
                    <span class="mono">php bin/backup.php</span> melalui cron pada jam sepi.
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Panduan Pemulihan</h2>
                <div class="card-sub">Uji cadangan secara berkala di server staging</div>
            </div>
        </div>
        <div class="card-body">
            <ol class="small" style="padding-left:18px;line-height:1.7">
                <li>Unduh file cadangan melalui tombol di tabel.</li>
                <li>Hentikan akses aplikasi (maintenance) agar tidak ada penulisan baru.</li>
                <li>Pulihkan dengan perintah:</li>
            </ol>
            <pre class="mono tiny" style="background:var(--bg);padding:12px;border-radius:8px;overflow:auto">mysql -u USER -p NAMA_DATABASE &lt; lju-finance-YYYYmmdd-HHMMSS.sql</pre>
            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">
                    File memakai <span class="mono">DROP TABLE IF EXISTS</span> sebelum membuat ulang tabel.
                    Jangan memulihkan ke database produksi yang masih dipakai tanpa pencadangan terbaru.
                    File <span class="mono">.sql</span> tidak dapat diakses langsung dari browser.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card--flush">
    <div class="card-head">
        <div>
            <h2 class="card-title">Riwayat Cadangan</h2>
            <div class="card-sub">Cadangan lebih lama dari <?= (int) $retensi ?> hari dihapus otomatis saat cadangan baru dibuat</div>
        </div>
        <span class="pill"><?= count($daftar) ?> file</span>
    </div>
    <?php if ($daftar === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('database', 22) ?></div>
            <h3>Belum ada cadangan</h3>
            <p>Buat cadangan pertama sebelum melakukan perubahan besar atau tutup buku.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th style="width:140px">Dibuat</th>
                        <th class="num" style="width:110px">Ukuran</th>
                        <th style="width:230px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $item): ?>
                        <tr>
                            <td class="mono small"><?= e((string) $item['file']) ?></td>
                            <td class="small"><?= e((string) $item['waktu']) ?></td>
                            <td class="num mono"><?= e($ukuran((int) $item['size'])) ?></td>
                            <td>
                                <div class="row" style="gap:6px">
                                    <a class="btn btn--sm" href="<?= e(url('system/backup/' . $item['file'] . '/download')) ?>">
                                        <?= icon('download', 14) ?> Unduh
                                    </a>
                                    <form method="post" action="<?= e(url('system/backup/' . $item['file'] . '/delete')) ?>"
                                          data-confirm="Hapus file cadangan ini?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn--sm btn--danger" type="submit"><?= icon('x', 14) ?> Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
