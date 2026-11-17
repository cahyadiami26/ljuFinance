<?php
/**
 * Bootstrap aplikasi: konfigurasi, autoloader, error handler, helper, sesi.
 */

declare(strict_types=1);

define('LJU_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('VIEW_PATH', __DIR__ . '/Views');
define('PUBLIC_PATH', BASE_PATH . '/assets');

$config = require BASE_PATH . '/config/config.php';

require __DIR__ . '/Core/Config.php';
\Lju\Core\Config::load($config);
unset($config);

date_default_timezone_set((string) \Lju\Core\Config::get('app.timezone', 'Asia/Jakarta'));

if (\Lju\Core\Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

/* ---------------------------------------------------------------- autoload */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Lju\\', 4) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 4));
    $file = APP_PATH . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Support/helpers.php';
require APP_PATH . '/Support/icons.php';

/* ------------------------------------------------------------ error handler */
set_exception_handler(static function (\Throwable $e): void {
    \Lju\Core\App::handleException($e);
});
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (\Lju\Core\Config::get('app.debug')) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    error_log('[LJU][php] ' . $message . ' @ ' . $file . ':' . $line);
    return true;
});

/* ------------------------------------------------------------------- sesi */
\Lju\Core\Auth::boot();
