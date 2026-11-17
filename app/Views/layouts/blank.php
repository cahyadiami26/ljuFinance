<?php
/**
 * Layout minimal untuk halaman login (mode pengembangan) / error.
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
    <title><?= e($appName) ?> · Portal Keuangan</title>
    <link rel="icon" href="<?= e(logo_url()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?= $content ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
