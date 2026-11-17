<?php
/**
 * LJU Finance & Accounting System — Front Controller
 * Semua request aplikasi masuk melalui file ini.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

\Lju\Core\Router::dispatchFromGlobals();
