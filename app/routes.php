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

use Lju\Controllers\AdvanceController;
use Lju\Controllers\ApController;
use Lju\Controllers\ApprovalController;
use Lju\Controllers\AssetController;
use Lju\Controllers\ArController;
use Lju\Controllers\ArController;
use Lju\Controllers\AuditController;
use Lju\Controllers\CashController;
use Lju\Controllers\ClosingController;
use Lju\Controllers\AuthController;
use Lju\Controllers\BudgetController;
use Lju\Controllers\CoaController;
use Lju\Controllers\DashboardController;
use Lju\Controllers\ExpenseController;
use Lju\Controllers\InventoryController;
use Lju\Controllers\JournalController;
use Lju\Controllers\LedgerController;
use Lju\Controllers\MasterController;
use Lju\Controllers\PeriodController;
use Lju\Controllers\ProcurementController;
use Lju\Controllers\ProjectController;
use Lju\Controllers\ReportController;
use Lju\Controllers\ProfileController;
use Lju\Controllers\ReconciliationController;
use Lju\Controllers\RevenueController;
use Lju\Controllers\RoleController;
use Lju\Controllers\TaxController;
use Lju\Controllers\SettingController;
use Lju\Controllers\TrialBalanceController;
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
/* -------------------------------------------------------- akuntansi inti */
// rute statis didaftarkan sebelum pola berparameter
$router->get('journal', [JournalController::class, 'index'], 'journal.view');
$router->get('journal/create', [JournalController::class, 'create'], 'journal.create');
$router->post('journal', [JournalController::class, 'store'], 'journal.create');
$router->get('journal/{id}', [JournalController::class, 'show'], 'journal.view');
$router->get('journal/{id}/edit', [JournalController::class, 'edit'], 'journal.create');
$router->post('journal/{id}', [JournalController::class, 'update'], 'journal.create');
$router->post('journal/{id}/submit', [JournalController::class, 'submit'], 'journal.submit');
$router->post('journal/{id}/approve', [JournalController::class, 'approve'], 'journal.approve');
$router->post('journal/{id}/reject', [JournalController::class, 'reject'], 'journal.approve');
$router->post('journal/{id}/post', [JournalController::class, 'post'], 'journal.post');
$router->post('journal/{id}/reverse', [JournalController::class, 'reverse'], 'journal.reverse');
$router->post('journal/{id}/delete', [JournalController::class, 'destroy'], 'journal.create');
$router->post('journal/{id}/attachment', [JournalController::class, 'upload'], 'journal.create');
$router->get('journal/{id}/attachment/{aid}', [JournalController::class, 'download'], 'journal.view');
$router->post('journal/{id}/attachment/{aid}/delete', [JournalController::class, 'deleteAttachment'], 'journal.create');

$router->get('ledger', [LedgerController::class, 'index'], 'journal.view');
$router->get('trial-balance', [TrialBalanceController::class, 'index'], 'journal.view');

$router->get('closing', [ClosingController::class, 'index'], 'period.view');
$router->post('closing/sync', [ClosingController::class, 'sync'], 'period.manage');
$router->post('closing/year', [ClosingController::class, 'closeYear'], 'period.manage');
$router->post('closing/{id}/close', [ClosingController::class, 'close'], 'period.manage');
$router->post('closing/{id}/lock', [ClosingController::class, 'lock'], 'period.manage');
$router->post('closing/{id}/reopen', [ClosingController::class, 'reopen'], 'period.manage');
/* ------------------------------------------------------------------ kas & bank */
$router->get('cash', [CashController::class, 'index'], 'cash.view');
$router->get('cash/create', [CashController::class, 'create'], 'cash.manage');
$router->post('cash', [CashController::class, 'store'], 'cash.manage');

$router->get('cash/reconciliation', [ReconciliationController::class, 'index'], 'cash.view');
$router->get('cash/reconciliation/create', [ReconciliationController::class, 'create'], 'cash.manage');
$router->post('cash/reconciliation', [ReconciliationController::class, 'store'], 'cash.manage');
$router->get('cash/reconciliation/{id}', [ReconciliationController::class, 'show'], 'cash.view');
$router->post('cash/reconciliation/{id}/match', [ReconciliationController::class, 'match'], 'cash.manage');
$router->post('cash/reconciliation/{id}/finish', [ReconciliationController::class, 'finish'], 'cash.manage');
$router->post('cash/reconciliation/{id}/delete', [ReconciliationController::class, 'destroy'], 'cash.manage');

$router->get('cash/advance', [AdvanceController::class, 'index'], 'advance.view');

$router->get('cash/{id}', [CashController::class, 'show'], 'cash.view');
$router->get('cash/{id}/edit', [CashController::class, 'edit'], 'cash.manage');
$router->post('cash/{id}', [CashController::class, 'update'], 'cash.manage');
$router->post('cash/{id}/submit', [CashController::class, 'submit'], 'cash.manage');
$router->post('cash/{id}/approve', [CashController::class, 'approve'], 'cash.approve');
$router->post('cash/{id}/reject', [CashController::class, 'reject'], 'cash.approve');
$router->post('cash/{id}/post', [CashController::class, 'post'], 'cash.post');
$router->post('cash/{id}/delete', [CashController::class, 'destroy'], 'cash.manage');

/* ------------------------------------------------------------------ uang muka */
$router->get('advance', [AdvanceController::class, 'index'], 'advance.view');
$router->get('advance/create', [AdvanceController::class, 'create'], 'advance.manage');
$router->post('advance', [AdvanceController::class, 'store'], 'advance.manage');
$router->get('advance/{id}', [AdvanceController::class, 'show'], 'advance.view');
$router->get('advance/{id}/edit', [AdvanceController::class, 'edit'], 'advance.manage');
$router->post('advance/{id}', [AdvanceController::class, 'update'], 'advance.manage');
$router->post('advance/{id}/submit', [AdvanceController::class, 'submit'], 'advance.manage');
$router->post('advance/{id}/approve', [AdvanceController::class, 'approve'], 'advance.approve');
$router->post('advance/{id}/reject', [AdvanceController::class, 'reject'], 'advance.approve');
$router->post('advance/{id}/cair', [AdvanceController::class, 'cair'], 'advance.manage');
$router->post('advance/{id}/items', [AdvanceController::class, 'items'], 'advance.manage');
$router->post('advance/{id}/settle', [AdvanceController::class, 'settle'], 'advance.manage');
$router->post('advance/{id}/delete', [AdvanceController::class, 'destroy'], 'advance.manage');

/* --------------------------------------------------------------- pengajuan biaya */
$router->get('expense', [ExpenseController::class, 'index'], 'expense.view');
$router->get('expense/create', [ExpenseController::class, 'create'], 'expense.manage');
$router->post('expense', [ExpenseController::class, 'store'], 'expense.manage');
$router->get('expense/{id}', [ExpenseController::class, 'show'], 'expense.view');
$router->get('expense/{id}/edit', [ExpenseController::class, 'edit'], 'expense.manage');
$router->post('expense/{id}', [ExpenseController::class, 'update'], 'expense.manage');
$router->post('expense/{id}/submit', [ExpenseController::class, 'submit'], 'expense.manage');
$router->post('expense/{id}/approve', [ExpenseController::class, 'approve'], 'expense.approve');
$router->post('expense/{id}/reject', [ExpenseController::class, 'reject'], 'expense.approve');
$router->post('expense/{id}/pay', [ExpenseController::class, 'pay'], 'expense.manage');
$router->post('expense/{id}/delete', [ExpenseController::class, 'destroy'], 'expense.manage');
/* ------------------------------------------------------------ hutang usaha (AP) */
$router->get('ap', [ApController::class, 'index'], 'ap.view');
$router->get('ap/create', [ApController::class, 'create'], 'ap.manage');
$router->post('ap', [ApController::class, 'store'], 'ap.manage');
$router->get('ap/{id}', [ApController::class, 'show'], 'ap.view');
$router->get('ap/{id}/edit', [ApController::class, 'edit'], 'ap.manage');
$router->post('ap/{id}', [ApController::class, 'update'], 'ap.manage');
$router->post('ap/{id}/submit', [ApController::class, 'submit'], 'ap.manage');
$router->post('ap/{id}/approve', [ApController::class, 'approve'], 'ap.approve');
$router->post('ap/{id}/reject', [ApController::class, 'reject'], 'ap.approve');
$router->post('ap/{id}/post', [ApController::class, 'post'], 'ap.manage');
$router->post('ap/{id}/pay', [ApController::class, 'pay'], 'ap.pay');
$router->post('ap/{id}/delete', [ApController::class, 'destroy'], 'ap.manage');

/* ------------------------------------------------------------ piutang usaha (AR) */
$router->get('ar', [ArController::class, 'index'], 'ar.view');
$router->get('ar/create', [ArController::class, 'create'], 'ar.manage');
$router->post('ar', [ArController::class, 'store'], 'ar.manage');
$router->get('ar/{id}', [ArController::class, 'show'], 'ar.view');
$router->get('ar/{id}/edit', [ArController::class, 'edit'], 'ar.manage');
$router->post('ar/{id}', [ArController::class, 'update'], 'ar.manage');
$router->post('ar/{id}/submit', [ArController::class, 'submit'], 'ar.manage');
$router->post('ar/{id}/approve', [ArController::class, 'approve'], 'ar.approve');
$router->post('ar/{id}/reject', [ArController::class, 'reject'], 'ar.approve');
$router->post('ar/{id}/post', [ArController::class, 'post'], 'ar.manage');
$router->post('ar/{id}/terima', [ArController::class, 'terima'], 'ar.manage');
$router->post('ar/{id}/delete', [ArController::class, 'destroy'], 'ar.manage');

/* -------------------------------------------------------- pengakuan pendapatan */
$router->get('revenue', [RevenueController::class, 'index'], 'ar.view');
$router->get('revenue/create', [RevenueController::class, 'create'], 'ar.manage');
$router->post('revenue', [RevenueController::class, 'store'], 'ar.manage');
$router->get('revenue/{id}', [RevenueController::class, 'show'], 'ar.view');
$router->get('revenue/{id}/edit', [RevenueController::class, 'edit'], 'ar.manage');
$router->post('revenue/{id}', [RevenueController::class, 'update'], 'ar.manage');
$router->post('revenue/{id}/submit', [RevenueController::class, 'submit'], 'ar.manage');
$router->post('revenue/{id}/approve', [RevenueController::class, 'approve'], 'ar.approve');
$router->post('revenue/{id}/reject', [RevenueController::class, 'reject'], 'ar.approve');
$router->post('revenue/{id}/post', [RevenueController::class, 'post'], 'ar.manage');
$router->post('revenue/{id}/delete', [RevenueController::class, 'destroy'], 'ar.manage');
/* ------------------------------------------------------ anggaran & pengendalian */
$router->get('budget', [BudgetController::class, 'index'], 'budget.view');
$router->get('budget/create', [BudgetController::class, 'create'], 'budget.manage');
$router->post('budget', [BudgetController::class, 'store'], 'budget.manage');

$router->get('budget/monitoring', [BudgetController::class, 'monitoring'], 'budget.view');

$router->get('budget/commitments', [BudgetController::class, 'commitments'], 'budget.view');
$router->post('budget/commitments', [BudgetController::class, 'commitmentStore'], 'budget.manage');
$router->post('budget/commitments/{id}/status', [BudgetController::class, 'commitmentStatus'], 'budget.manage');
$router->post('budget/commitments/{id}/delete', [BudgetController::class, 'commitmentDestroy'], 'budget.manage');

$router->get('budget/{id}', [BudgetController::class, 'show'], 'budget.view');
$router->get('budget/{id}/edit', [BudgetController::class, 'edit'], 'budget.manage');
$router->post('budget/{id}', [BudgetController::class, 'update'], 'budget.manage');
$router->post('budget/{id}/submit', [BudgetController::class, 'submit'], 'budget.manage');
$router->post('budget/{id}/approve', [BudgetController::class, 'approve'], 'budget.approve');
$router->post('budget/{id}/reject', [BudgetController::class, 'reject'], 'budget.approve');
$router->post('budget/{id}/activate', [BudgetController::class, 'activate'], 'budget.approve');
$router->post('budget/{id}/archive', [BudgetController::class, 'archive'], 'budget.approve');
$router->post('budget/{id}/revise', [BudgetController::class, 'revise'], 'budget.manage');
$router->post('budget/{id}/delete', [BudgetController::class, 'destroy'], 'budget.manage');

/* ------------------------------------------------------------- approval center */
$router->get('approval', [ApprovalController::class, 'index'], 'approval.view');
$router->get('approval/delegations', [ApprovalController::class, 'delegations'], 'approval.view');
$router->post('approval/delegations', [ApprovalController::class, 'delegationStore'], 'approval.act');
$router->post('approval/delegations/{id}/close', [ApprovalController::class, 'delegationClose'], 'approval.act');
$router->get('approval/{id}', [ApprovalController::class, 'show'], 'approval.view');
$router->post('approval/{id}/act', [ApprovalController::class, 'act'], 'approval.act');
$router->post('approval/{id}/cancel', [ApprovalController::class, 'cancel'], 'approval.act');
/* --------------------------------------------------------------- aset tetap */
$router->get('asset', [AssetController::class, 'index'], 'asset.view');
$router->get('asset/create', [AssetController::class, 'create'], 'asset.manage');
$router->post('asset', [AssetController::class, 'store'], 'asset.manage');

$router->get('asset/depreciation', [AssetController::class, 'depreciation'], 'asset.view');
$router->post('asset/depreciation', [AssetController::class, 'depreciationStore'], 'asset.manage');
$router->post('asset/depreciation/post', [AssetController::class, 'depreciationPost'], 'asset.manage');
$router->post('asset/depreciation/clear', [AssetController::class, 'depreciationClear'], 'asset.manage');

$router->get('asset/{id}', [AssetController::class, 'show'], 'asset.view');
$router->get('asset/{id}/edit', [AssetController::class, 'edit'], 'asset.manage');
$router->post('asset/{id}', [AssetController::class, 'update'], 'asset.manage');
$router->post('asset/{id}/activate', [AssetController::class, 'activate'], 'asset.manage');
$router->post('asset/{id}/deactivate', [AssetController::class, 'deactivate'], 'asset.manage');
$router->post('asset/{id}/location', [AssetController::class, 'location'], 'asset.manage');
$router->post('asset/{id}/sell', [AssetController::class, 'sell'], 'asset.manage');
$router->post('asset/{id}/dispose', [AssetController::class, 'dispose'], 'asset.manage');
$router->post('asset/{id}/delete', [AssetController::class, 'destroy'], 'asset.manage');

/* --------------------------------------------------------------- perpajakan */
$router->get('tax', [TaxController::class, 'index'], 'tax.view');
$router->get('tax/create', [TaxController::class, 'create'], 'tax.manage');
$router->post('tax', [TaxController::class, 'store'], 'tax.manage');
$router->get('tax/report', [TaxController::class, 'report'], 'tax.view');
$router->post('tax/report/generate', [TaxController::class, 'reportGenerate'], 'tax.manage');
$router->post('tax/report/status', [TaxController::class, 'reportStatus'], 'tax.manage');
$router->get('tax/efaktur', [TaxController::class, 'efaktur'], 'tax.view');
$router->get('tax/{id}', [TaxController::class, 'show'], 'tax.view');
$router->get('tax/{id}/edit', [TaxController::class, 'edit'], 'tax.manage');
$router->post('tax/{id}', [TaxController::class, 'update'], 'tax.manage');
$router->post('tax/{id}/post', [TaxController::class, 'posting'], 'tax.manage');
$router->post('tax/{id}/setor', [TaxController::class, 'setor'], 'tax.manage');
$router->post('tax/{id}/lapor', [TaxController::class, 'lapor'], 'tax.manage');
$router->post('tax/{id}/void', [TaxController::class, 'void'], 'tax.manage');
$router->post('tax/{id}/delete', [TaxController::class, 'destroy'], 'tax.manage');

/* ------------------------------------------------- pengadaan & persediaan */
$router->get('procurement', [ProcurementController::class, 'index'], 'procurement.view');
$router->get('procurement/create', [ProcurementController::class, 'create'], 'procurement.manage');
$router->post('procurement', [ProcurementController::class, 'store'], 'procurement.manage');
$router->get('procurement/{id}/edit', [ProcurementController::class, 'edit'], 'procurement.manage');
$router->post('procurement/{id}/ajukan', [ProcurementController::class, 'ajukan'], 'procurement.manage');
$router->post('procurement/{id}/setujui', [ProcurementController::class, 'setujui'], 'procurement.approve');
$router->post('procurement/{id}/batal', [ProcurementController::class, 'batal'], 'procurement.approve');
$router->post('procurement/{id}/hapus', [ProcurementController::class, 'hapus'], 'procurement.manage');
$router->get('procurement/{id}/terima', [ProcurementController::class, 'terimaForm'], 'procurement.manage');
$router->post('procurement/{id}/terima', [ProcurementController::class, 'terimaStore'], 'procurement.manage');
$router->post('procurement/{id}/tagih', [ProcurementController::class, 'tagih'], 'procurement.manage');
$router->post('procurement/{id}', [ProcurementController::class, 'update'], 'procurement.manage');
$router->get('procurement/{id}', [ProcurementController::class, 'show'], 'procurement.view');

$router->get('inventory', [InventoryController::class, 'index'], 'inventory.view');
$router->get('inventory/movements', [InventoryController::class, 'movements'], 'inventory.view');
$router->get('inventory/item/create', [InventoryController::class, 'create'], 'inventory.manage');
$router->post('inventory/item', [InventoryController::class, 'store'], 'inventory.manage');
$router->get('inventory/item/{id}/edit', [InventoryController::class, 'edit'], 'inventory.manage');
$router->get('inventory/item/{id}/masuk', [InventoryController::class, 'masukForm'], 'inventory.manage');
$router->post('inventory/item/{id}/masuk', [InventoryController::class, 'masukStore'], 'inventory.manage');
$router->get('inventory/item/{id}/keluar', [InventoryController::class, 'keluarForm'], 'inventory.manage');
$router->post('inventory/item/{id}/keluar', [InventoryController::class, 'keluarStore'], 'inventory.manage');
$router->post('inventory/item/{id}', [InventoryController::class, 'update'], 'inventory.manage');
$router->get('inventory/item/{id}', [InventoryController::class, 'show'], 'inventory.view');

/* ------------------------------------------------------- proyek & kontrak */
$router->get('project', [ProjectController::class, 'index'], 'project.view');
$router->get('project/create', [ProjectController::class, 'create'], 'project.manage');
$router->post('project', [ProjectController::class, 'store'], 'project.manage');
$router->get('project/contracts', [ProjectController::class, 'contracts'], 'project.view');
$router->get('project/contract/create', [ProjectController::class, 'contractCreate'], 'project.manage');
$router->post('project/contract', [ProjectController::class, 'contractStore'], 'project.manage');
$router->get('project/contract/{id}/edit', [ProjectController::class, 'contractEdit'], 'project.manage');
$router->post('project/contract/{id}/status', [ProjectController::class, 'contractStatus'], 'project.manage');
$router->post('project/contract/{id}/tagih', [ProjectController::class, 'tagihTermin'], 'project.manage');
$router->post('project/contract/{id}', [ProjectController::class, 'contractUpdate'], 'project.manage');
$router->get('project/contract/{id}', [ProjectController::class, 'contractShow'], 'project.view');
$router->get('project/{id}/edit', [ProjectController::class, 'edit'], 'project.manage');
$router->post('project/{id}', [ProjectController::class, 'update'], 'project.manage');
$router->get('project/{id}', [ProjectController::class, 'show'], 'project.view');

/* ---------------------------------------------------- laporan keuangan */
$router->get('report', [ReportController::class, 'index'], 'report.view');
$router->get('report/income', [ReportController::class, 'income'], 'report.view');
$router->get('report/balance-sheet', [ReportController::class, 'balanceSheet'], 'report.view');
$router->get('report/cash-flow', [ReportController::class, 'cashFlow'], 'report.view');
$router->get('report/equity', [ReportController::class, 'equity'], 'report.view');
$router->get('report/project', [ReportController::class, 'project'], 'report.view');
$router->get('report/unit-usaha', [ReportController::class, 'unitUsaha'], 'report.view');
$router->get('report/print/{jenis}', [ReportController::class, 'print'], 'report.view');
$router->get('report/export/{jenis}', [ReportController::class, 'export'], 'report.export');
