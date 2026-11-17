<?php
/**
 * Halaman kesalahan.
 *
 * @var int $status
 * @var string $message
 * @var string $title
 */
$status = (int) ($status ?? 500);
$messages = [
    403 => 'Anda tidak memiliki kewenangan untuk mengakses halaman ini. Hubungi Kepala Bidang Keuangan bila membutuhkan akses.',
    404 => 'Halaman atau data yang Anda cari tidak ditemukan.',
    419 => 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.',
    500 => 'Terjadi kesalahan pada server. Tim teknis telah menerima catatan kejadian ini.',
];
?>
<div class="card" style="max-width:640px;margin:32px auto;text-align:center">
    <div class="card-body" style="padding:44px 32px">
        <div class="icon-wrap" style="width:62px;height:62px;border-radius:18px;margin:0 auto 16px;display:grid;place-items:center;background:var(--danger-soft);color:var(--danger)">
            <?= icon('alert', 26) ?>
        </div>
        <div style="font-size:40px;font-weight:800;letter-spacing:-.04em;color:var(--ink);line-height:1"><?= $status ?></div>
        <h2 style="margin:8px 0 8px;font-size:17px"><?= e((string) ($title ?? 'Terjadi kesalahan')) ?></h2>
        <p class="small muted" style="max-width:420px;margin:0 auto 18px">
            <?= e($message !== '' ? $message : ($messages[$status] ?? 'Silakan coba beberapa saat lagi.')) ?>
        </p>
        <div class="row" style="justify-content:center;gap:8px">
            <a class="btn btn--primary" href="<?= e(url('dashboard')) ?>"><?= icon('dashboard', 15) ?> Dashboard</a>
            <a class="btn" href="javascript:history.back()">Kembali</a>
        </div>
    </div>
</div>
