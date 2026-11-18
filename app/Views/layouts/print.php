<?php
/**
 * Layout khusus cetak laporan keuangan.
 *
 * @var string $content
 */
$appName = (string) setting('app_name', config('app.name', 'LJU Finance'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> · Cetak Laporan</title>
    <link rel="icon" href="<?= e(logo_url()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
    <a class="btn" href="javascript:history.back()"><?= icon('arrow-left', 15) ?> Kembali</a>
    <span class="spacer"></span>
    <button class="btn btn--primary" type="button" onclick="window.print()"><?= icon('printer', 15) ?> Cetak / Simpan PDF</button>
</div>

<main class="print-page">
    <?= $content ?>
</main>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
