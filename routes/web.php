<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CallbackApiKeySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DynamicVirtualAccountController;
use App\Http\Controllers\FixedVirtualAccountController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PaymentTransactionController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'no-cache'])->group(function (): void {
    Route::get('/login', [AuthController::class, 'index'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/forgot-password', [PasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->name('password.store');
});

Route::get('/payment/{invoice}', [InvoiceController::class, 'payment'])
    ->middleware('signed')
    ->name('payment.show');

Route::middleware(['auth', 'auth.session', 'active', 'no-cache'])->group(function (): void {
    Route::get('/', fn (Request $request) => redirect()->route($request->user()->landingRouteName()));
    Route::get('/home', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('home');
    Route::get('/change-password', [PasswordController::class, 'editCurrent'])->name('password.change');
    Route::put('/change-password', [PasswordController::class, 'updateCurrent'])->name('password.change.update');
    Route::get('/settings/branches', [BranchController::class, 'index'])
        ->middleware('permission:branches.view')
        ->name('settings.branches.index');
    Route::get('/settings/branches/create', [BranchController::class, 'create'])
        ->middleware('permission:branches.create')
        ->name('settings.branches.create');
    Route::post('/settings/branches', [BranchController::class, 'store'])
        ->middleware('permission:branches.create')
        ->name('settings.branches.store');
    Route::get('/settings/branches/{branch}/edit', [BranchController::class, 'edit'])
        ->middleware('permission:branches.update')
        ->name('settings.branches.edit');
    Route::put('/settings/branches/{branch}', [BranchController::class, 'update'])
        ->middleware('permission:branches.update')
        ->name('settings.branches.update');
    Route::post('/settings/branches/{branch}/rotate-api-key', [BranchController::class, 'rotateApiKey'])
        ->middleware('permission:branches.update')
        ->name('settings.branches.rotate-api-key');
    Route::get('/settings/callback-api-key', [CallbackApiKeySettingsController::class, 'index'])
        ->middleware('permission:callback_api_keys.view')
        ->name('settings.callback-api-key.index');
    Route::post('/settings/callback-api-key', [CallbackApiKeySettingsController::class, 'store'])
        ->middleware('permission:callback_api_keys.update')
        ->name('settings.callback-api-key.store');
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoices.view')
        ->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('permission:invoices.view')
        ->name('invoices.show');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'voidInvoice'])
        ->middleware('permission:invoices.delete')
        ->name('invoices.void');
    Route::get('/transaction', [PaymentTransactionController::class, 'index'])
        ->middleware('permission:transactions.view')
        ->name('transactions.index');
    Route::get('/transaction/{paymentTransaction}', [PaymentTransactionController::class, 'show'])
        ->middleware('permission:transactions.view')
        ->name('transactions.show');
    Route::post('/transaction/{paymentTransaction}/callback', [PaymentTransactionController::class, 'retryBranchWebhook'])
        ->middleware('permission:transactions.update')
        ->name('transactions.callback.retry');
    Route::get('/reconciliations', [BankReconciliationController::class, 'index'])
        ->middleware('permission:reconciliations.view')
        ->name('reconciliations.index');
    Route::get('/reconciliations/template', [BankReconciliationController::class, 'template'])
        ->middleware('permission:reconciliations.view')
        ->name('reconciliations.template');
    Route::post('/reconciliations', [BankReconciliationController::class, 'store'])
        ->middleware('permission:reconciliations.create')
        ->name('reconciliations.store');
    Route::get('/reconciliations/{reconciliation}', [BankReconciliationController::class, 'show'])
        ->middleware('permission:reconciliations.view')
        ->name('reconciliations.show');
    Route::get('/virtual-accounts/fixed', [FixedVirtualAccountController::class, 'index'])
        ->middleware('permission:fixed_virtual_accounts.view')
        ->name('virtual-accounts.fixed.index');
    Route::get('/virtual-accounts/dynamic', [DynamicVirtualAccountController::class, 'index'])
        ->middleware('permission:dynamic_virtual_accounts.view')
        ->name('virtual-accounts.dynamic.index');
    Route::get('/virtual-accounts/fixed/create', [FixedVirtualAccountController::class, 'create'])
        ->middleware('permission:fixed_virtual_accounts.create')
        ->name('virtual-accounts.fixed.create');
    Route::post('/virtual-accounts/fixed', [FixedVirtualAccountController::class, 'store'])
        ->middleware('permission:fixed_virtual_accounts.create')
        ->name('virtual-accounts.fixed.store');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit_logs.view')
        ->name('audit-logs.index');
    Route::get('/reports', [ReportsController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])
        ->middleware('permission:reports.view')
        ->name('reports.export');

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

        Route::get('/roles', [RoleManagementController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleManagementController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleManagementController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleManagementController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])->name('roles.destroy');
    });
});
