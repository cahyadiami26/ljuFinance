<?php
/**
 * Installer LJU Finance & Accounting System.
 *
 * Membuka: https://finance.lju.co.id/install.php?token=TOKEN
 * Token diambil dari config/config.php → security.install_token.
 *
 * Installer akan:
 *  1. memeriksa kebutuhan sistem (PHP, ekstensi, izin folder),
 *  2. membuat database & mengimport skema + seed referensi (+ seed demo opsional),
 *  3. menulis config/config.local.php.
 *
 * SETELAH SELESAI: hapus atau rename file ini.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require __DIR__ . '/config/config.php';
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$expected = (string) ($config['security']['install_token'] ?? '');
$authorized = $expected !== '' && hash_equals($expected, $token);
$installed = is_file(__DIR__ . '/config/config.local.php');

/* ------------------------------------------------------------------ helpers */

/** Pisahkan dump SQL menjadi statement (menghormati quote & komentar). */
function splitSql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inSingle) {
            $buffer .= $char;
            if ($char === '\\') {
                if ($next !== '') {
                    $buffer .= $next;
                    $i++;
                }
            } elseif ($char === "'") {
                $inSingle = false;
            }
            continue;
        }
        if ($inDouble) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
            } elseif ($char === '"') {
                $inDouble = false;
            }
            continue;
        }
        if ($inBacktick) {
            $buffer .= $char;
            if ($char === '`') {
                $inBacktick = false;
            }
            continue;
        }

        // komentar baris
        if ($char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }
        // komentar blok
        if ($char === '/' && $next === '*') {
            $i += 2;
            while ($i < $length - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            continue;
        }

        if ($char === "'") {
            $inSingle = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '"') {
            $inDouble = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '`') {
            $inBacktick = true;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

/** @return array<int,array{ok:bool,label:string,detail:string}> */
function runSqlFile(PDO $pdo, string $path): array
{
    $results = [];
    $sql = (string) file_get_contents($path);
    $statements = splitSql($sql);
    $executed = 0;

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (Throwable $e) {
            $results[] = [
                'ok'     => false,
                'label'  => basename($path),
                'detail' => 'Statement gagal: ' . $e->getMessage() . ' — ' . mb_substr($statement, 0, 140),
            ];
        }
    }

    array_unshift($results, [
        'ok'     => true,
        'label'  => basename($path),
        'detail' => $executed . ' statement dieksekusi.',
    ]);

    return $results;
}

/* ------------------------------------------------------------------- proses */

$errors = [];
$results = [];
$form = [
    'host'   => $_POST['host'] ?? ($config['db']['host'] ?? 'localhost'),
    'port'   => $_POST['port'] ?? ($config['db']['port'] ?? 3306),
    'name'   => $_POST['name'] ?? ($config['db']['name'] ?? 'lju_finance'),
    'user'   => $_POST['user'] ?? ($config['db']['user'] ?? 'root'),
    'pass'   => $_POST['pass'] ?? '',
    'sso_db' => $_POST['sso_db'] ?? ($config['sso']['db'] ?? 'lju_portal'),
    'demo'   => isset($_POST['demo']),
    'dev'    => isset($_POST['dev']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authorized) {
    foreach (['host', 'name', 'user'] as $field) {
        if (trim((string) $form[$field]) === '') {
            $errors[] = 'Kolom "' . $field . '" wajib diisi.';
        }
    }
    if ($form['name'] !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', (string) $form['name'])) {
        $errors[] = 'Nama database hanya boleh huruf, angka, garis bawah, dan tanda hubung.';
    }
    if ($installed && empty($_POST['force'])) {
        $errors[] = 'Aplikasi sudah terpasang (config/config.local.php ada). Centang "paksa pasang ulang" bila memang ingin mengulang.';
    }

    if ($errors === []) {
        try {
            $host = (string) $form['host'];
            $port = (int) $form['port'];
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
            $pdo = new PDO($dsn, (string) $form['user'], (string) $form['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $dbName = (string) $form['name'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $results[] = ['ok' => true, 'label' => 'Database', 'detail' => 'Database `' . $dbName . '` siap digunakan.'];

            $schemaFiles = glob(__DIR__ . '/database/schema/*.sql') ?: [];
            sort($schemaFiles);
            foreach ($schemaFiles as $file) {
                $results = array_merge($results, runSqlFile($pdo, $file));
            }

            $reference = __DIR__ . '/database/seed/01_reference.sql';
            if (is_file($reference)) {
                $results = array_merge($results, runSqlFile($pdo, $reference));
            }

            $demo = __DIR__ . '/database/seed/02_demo.sql';
            if ($form['demo'] && is_file($demo)) {
                $results = array_merge($results, runSqlFile($pdo, $demo));
            }

            // tulis konfigurasi lokal
            $export = [
                'db'  => [
                    'host' => $host,
                    'port' => $port,
                    'name' => $dbName,
                    'user' => (string) $form['user'],
                    'pass' => (string) $form['pass'],
                ],
                'sso' => ['db' => (string) $form['sso_db']],
                'app' => [
                    'env'   => $form['dev'] ? 'local' : 'production',
                    'debug' => (bool) $form['dev'],
                ],
            ];
            if ($form['dev']) {
                $export['sso']['dev_login'] = true;
            }

            $content = "<?php\n/**\n * Konfigurasi lokal — dibuat otomatis oleh install.php pada "
                . date('Y-m-d H:i:s') . ".\n * File ini tidak di-commit (lihat .gitignore).\n */\n\nreturn "
                . var_export($export, true) . ";\n";

            if (@file_put_contents(__DIR__ . '/config/config.local.php', $content) === false) {
                $errors[] = 'Gagal menulis config/config.local.php — pastikan folder config/ dapat ditulis.';
            } else {
                $results[] = ['ok' => true, 'label' => 'Konfigurasi', 'detail' => 'config/config.local.php berhasil dibuat.'];
            }
        } catch (Throwable $e) {
            $errors[] = 'Instalasi gagal: ' . $e->getMessage();
        }
    }
}

/* -------------------------------------------------------------- requirement */

$requirements = [
    'PHP >= 8.0'          => version_compare(PHP_VERSION, '8.0.0', '>='),
    'ekstensi pdo_mysql'  => extension_loaded('pdo_mysql'),
    'ekstensi mbstring'   => extension_loaded('mbstring'),
    'ekstensi json'       => extension_loaded('json'),
    'folder config/ writable' => is_writable(__DIR__ . '/config'),
    'folder uploads/ writable' => is_writable(__DIR__ . '/uploads'),
    'folder storage/ writable' => is_writable(__DIR__ . '/storage'),
];

$failed = count(array_filter($requirements, static fn ($ok) => !$ok));
$tokenHint = htmlspecialchars($expected, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalasi · LJU Finance</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .install-wrap { max-width: 880px; margin: 0 auto; padding: 32px 20px 60px; }
        .install-head { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .install-head img { width:40px;height:40px;object-fit:contain;background:#0f172a;border-radius:10px;padding:4px }
        .req { display:flex; align-items:center; justify-content:space-between; padding:9px 0; border-bottom:1px solid var(--border); font-size:13px }
        .code { font-family: ui-monospace, monospace; background:#0f172a; color:#e2e8f0; padding:10px 12px; border-radius:8px; font-size:12px; overflow:auto }
    </style>
</head>
<body>
<div class="install-wrap">
    <div class="install-head">
        <img src="<?= htmlspecialchars($config['assets']['logo_url'], ENT_QUOTES) ?>" alt="LJU" onerror="this.style.visibility='hidden'">
        <div>
            <h1 style="font-size:20px">Instalasi LJU Finance &amp; Accounting System</h1>
            <div class="small muted">PT. Lampung Jasa Utama · versi <?= htmlspecialchars((string) $config['app']['version']) ?></div>
        </div>
    </div>

    <?php if (!$authorized): ?>
        <div class="card">
            <div class="card-body" style="padding-top:20px">
                <div class="notice notice--warning">
                    <?= '&#9888;' ?>
                    <div>
                        <strong>Akses installer membutuhkan token.</strong><br>
                        Buka halaman ini dengan parameter token sesuai <span class="code-pill">config/config.php</span> → <span class="code-pill">security.install_token</span>, contoh:
                    </div>
                </div>
                <pre class="code mt-12">https://finance.lju.co.id/install.php?token=<?= $tokenHint ?></pre>
                <p class="small muted mt-12" style="margin-top:14px">
                    Setelah instalasi selesai, hapus file <span class="code-pill">install.php</span> dan ganti token default.
                </p>
            </div>
        </div>
        <?php exit; ?>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="flash flash--error mb-16">
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($results !== []): ?>
        <div class="card mb-16">
            <div class="card-head"><h2 class="card-title">Hasil instalasi</h2></div>
            <div class="card-body">
                <?php foreach ($results as $result): ?>
                    <div class="req">
                        <span><?= $result['ok'] ? '<span style="color:var(--success)">✔</span>' : '<span style="color:var(--danger)">✖</span>' ?> <?= htmlspecialchars($result['label']) ?></span>
                        <span class="tiny muted" style="text-align:right;max-width:60%"><?= htmlspecialchars($result['detail']) ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ($errors === []): ?>
                    <div class="notice notice--info mt-16">
                        <div>
                            <strong>Instalasi selesai.</strong> Hapus <span class="code-pill">install.php</span>, lalu buka
                            <a href="index.php">aplikasi</a>. Login produksi melalui portal SSO; untuk mode pengembangan aktifkan
                            <span class="code-pill">dev_login</span> pada config.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid-half">
        <div class="card">
            <div class="card-head"><h2 class="card-title">Kebutuhan sistem</h2></div>
            <div class="card-body">
                <?php foreach ($requirements as $label => $ok): ?>
                    <div class="req">
                        <span><?= $ok ? '<span style="color:var(--success)">✔</span>' : '<span style="color:var(--danger)">✖</span>' ?> <?= htmlspecialchars($label) ?></span>
                        <span class="tiny muted"><?= $ok ? 'OK' : 'Perlu diperbaiki' ?></span>
                    </div>
                <?php endforeach; ?>
                <p class="tiny muted mt-12">domPDF (opsional, untuk ekspor PDF) dipasang manual di <span class="code-pill">vendor/dompdf/</span>.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Konfigurasi database</h2></div>
            <div class="card-body">
                <form method="post" action="install.php?token=<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                    <div class="form-grid" style="grid-template-columns:1fr 1fr">
                        <div class="field"><label class="field-label">Host</label>
                            <input class="input" name="host" value="<?= htmlspecialchars((string) $form['host'], ENT_QUOTES) ?>"></div>
                        <div class="field"><label class="field-label">Port</label>
                            <input class="input" name="port" value="<?= htmlspecialchars((string) $form['port'], ENT_QUOTES) ?>"></div>
                        <div class="field"><label class="field-label">Database</label>
                            <input class="input" name="name" value="<?= htmlspecialchars((string) $form['name'], ENT_QUOTES) ?>"></div>
                        <div class="field"><label class="field-label">Database SSO</label>
                            <input class="input" name="sso_db" value="<?= htmlspecialchars((string) $form['sso_db'], ENT_QUOTES) ?>"></div>
                        <div class="field"><label class="field-label">Username</label>
                            <input class="input" name="user" value="<?= htmlspecialchars((string) $form['user'], ENT_QUOTES) ?>"></div>
                        <div class="field"><label class="field-label">Password</label>
                            <input class="input" type="password" name="pass" value=""></div>
                    </div>

                    <div class="stack-8 mt-16">
                        <label class="checkbox"><input type="checkbox" name="demo" value="1" <?= $form['demo'] ? 'checked' : '' ?>> Import data contoh (dashboard langsung berisi)</label>
                        <label class="checkbox"><input type="checkbox" name="dev" value="1" <?= $form['dev'] ? 'checked' : '' ?>> Aktifkan mode pengembangan (dev login, tanpa SSO)</label>
                        <?php if ($installed): ?>
                            <label class="checkbox"><input type="checkbox" name="force" value="1"> Paksa pasang ulang</label>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn--primary" type="submit" <?= $failed > 0 ? 'disabled' : '' ?>>Jalankan Instalasi</button>
                    </div>
                    <?php if ($failed > 0): ?>
                        <p class="tiny" style="color:var(--danger)">Perbaiki kebutuhan sistem terlebih dahulu.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <p class="tiny muted mt-16">
        Peringatan: instalasi ulang di database yang sudah berisi data dapat menimpa data referensi (seed bersifat idempotent) —
        buat backup terlebih dahulu.
    </p>
</div>
</body>
</html>
