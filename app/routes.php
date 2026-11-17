<?php
/**
 * Tabel rute aplikasi.
 *
 * Format: $router->get(post)('path', [Controller::class, 'method'], 'permission', ['option' => ...])
 * Permission '' = hanya butuh login.
 * Option 'public' => true = tidak butuh login (halaman login SSO/dev).
 *
 * @var \Lju\Core\Router $router
 */

use Lju\Controllers\AuditController;
use Lju\Controllers\AuthController;
use Lju\Controllers\CoaController;
use Lju\Controllers\DashboardController;
use Lju\Controllers\MasterController;
use Lju\Controllers\PeriodController;
use Lju\Controllers\PlaceholderController;
use Lju\Controllers\ProfileController;
use Lju\Controllers\RoleController;
use Lju\Controllers\SettingController;
use Lju\Controllers\UserController;

/* --------------------------------------------------------------- autentikasi */
$router->get('login', [AuthController::class, 'loginForm'], null, ['public' => true]);
$router->post('login', [AuthController::class, 'login'], null, ['public' => true]);
$router->get('logout', [AuthController::class, 'logout'], null, ['public' => true]);

/* ----------------------------------------------------------------- dashboard */
$router->get('dashboard', [DashboardController::class, 'index'], 'dashboard.view');
$router->get('dashboard/refresh', [DashboardController::class, 'refresh'], 'dashboard.view');

/* -------------------------------------------------------------------- profil */
$router->get('profile', [ProfileController::class, 'index'], '');
$router->post('profile/sync', [ProfileController::class, 'sync'], '');

/* ----------------------------------------------------------- master: khusus */
// rute spesifik harus didaftarkan sebelum pola generik master/{resource}
$router->get('master/coa', [CoaController::class, 'index'], 'coa.view');
$router->get('master/coa/create', [CoaController::class, 'create'], 'coa.manage');
$router->post('master/coa', [CoaController::class, 'store'], 'coa.manage');
$router->get('master/coa/{id}/edit', [CoaController::class, 'edit'], 'coa.manage');
$router->post('master/coa/{id}', [CoaController::class, 'update'], 'coa.manage');
$router->post('master/coa/{id}/toggle', [CoaController::class, 'toggle'], 'coa.manage');

$router->get('master/period', [PeriodController::class, 'index'], 'period.view');
$router->post('master/period/{id}/status', [PeriodController::class, 'updateStatus'], 'period.manage');

$router->get('master/user', [UserController::class, 'index'], 'user.view');
$router->get('master/user/{id}/edit', [UserController::class, 'edit'], 'user.manage');
$router->post('master/user/{id}/role', [UserController::class, 'updateRole'], 'user.manage');
$router->post('master/user/{id}/toggle', [UserController::class, 'toggle'], 'user.manage');

$router->get('master/role', [RoleController::class, 'index'], 'role.manage');
$router->post('master/role/{id}', [RoleController::class, 'update'], 'role.manage');

/* ---------------------------------------------------- master: generik (CRUD) */
$router->get('master/{resource}', [MasterController::class, 'index'], '');
$router->get('master/{resource}/create', [MasterController::class, 'create'], '');
$router->post('master/{resource}', [MasterController::class, 'store'], '');
$router->get('master/{resource}/{id}/edit', [MasterController::class, 'edit'], '');
$router->post('master/{resource}/{id}', [MasterController::class, 'update'], '');
$router->post('master/{resource}/{id}/toggle', [MasterController::class, 'toggle'], '');

/* -------------------------------------------------------------- sistem */
$router->get('system/audit', [AuditController::class, 'index'], 'audit.view');
$router->get('system/settings', [SettingController::class, 'index'], 'settings.manage');
$router->post('system/settings', [SettingController::class, 'update'], 'settings.manage');

/* ------------------------------------------- modul tahap berikutnya (placeholder) */
$router->get('journal', [PlaceholderController::class, 'show'], 'journal.view');
$router->get('journal/create', [PlaceholderController::class, 'show'], 'journal.create');
$router->get('ledger', [PlaceholderController::class, 'show'], 'journal.view');
$router->get('trial-balance', [PlaceholderController::class, 'show'], 'journal.view');
$router->get('closing', [PlaceholderController::class, 'show'], 'period.manage');
$router->get('cash', [PlaceholderController::class, 'show'], 'cash.view');
$router->get('cash/advance', [PlaceholderController::class, 'show'], 'advance.view');
$router->get('expense', [PlaceholderController::class, 'show'], 'expense.view');
$router->get('ap', [PlaceholderController::class, 'show'], 'ap.view');
$router->get('ar', [PlaceholderController::class, 'show'], 'ar.view');
$router->get('budget', [PlaceholderController::class, 'show'], 'budget.view');
$router->get('approval', [PlaceholderController::class, 'show'], 'approval.view');
$router->get('asset', [PlaceholderController::class, 'show'], 'asset.view');
$router->get('tax', [PlaceholderController::class, 'show'], 'tax.view');
$router->get('report', [PlaceholderController::class, 'show'], 'report.view');
