<?php

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

Route::middleware(['auth', 'auth.session', 'role:super_admin', 'no-cache'])->group(function (): void {
    Route::get('/', fn () => redirect()->route('home'));
    Route::get('/home', [DashboardController::class, 'index'])->name('home');
    Route::get('/change-password', [PasswordController::class, 'editCurrent'])->name('password.change');
    Route::put('/change-password', [PasswordController::class, 'updateCurrent'])->name('password.change.update');
    Route::get('/settings/branches', [BranchController::class, 'index'])->name('settings.branches.index');
    Route::get('/settings/branches/create', [BranchController::class, 'create'])->name('settings.branches.create');
    Route::post('/settings/branches', [BranchController::class, 'store'])->name('settings.branches.store');
    Route::get('/settings/branches/{branch}/edit', [BranchController::class, 'edit'])->name('settings.branches.edit');
    Route::put('/settings/branches/{branch}', [BranchController::class, 'update'])->name('settings.branches.update');
    Route::post('/settings/branches/{branch}/rotate-api-key', [BranchController::class, 'rotateApiKey'])
        ->name('settings.branches.rotate-api-key');
    Route::get('/settings/callback-api-key', [CallbackApiKeySettingsController::class, 'index'])
        ->name('settings.callback-api-key.index');
    Route::post('/settings/callback-api-key', [CallbackApiKeySettingsController::class, 'store'])
        ->name('settings.callback-api-key.store');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');
    Route::get('/payment-transactions', [PaymentTransactionController::class, 'index'])
        ->name('payment-transactions.index');
    Route::get('/payment-transactions/{paymentTransaction}', [PaymentTransactionController::class, 'show'])
        ->name('payment-transactions.show');
    Route::post('/payment-transactions/{paymentTransaction}/callback', [PaymentTransactionController::class, 'retryBranchWebhook'])
        ->name('payment-transactions.callback.retry');
    Route::get('/reconciliations', [BankReconciliationController::class, 'index'])
        ->name('reconciliations.index');
    Route::get('/reconciliations/template', [BankReconciliationController::class, 'template'])
        ->name('reconciliations.template');
    Route::post('/reconciliations', [BankReconciliationController::class, 'store'])
        ->name('reconciliations.store');
    Route::get('/reconciliations/{reconciliation}', [BankReconciliationController::class, 'show'])
        ->name('reconciliations.show');
    Route::get('/virtual-accounts/fixed', [FixedVirtualAccountController::class, 'index'])
        ->name('virtual-accounts.fixed.index');
    Route::get('/virtual-accounts/dynamic', [DynamicVirtualAccountController::class, 'index'])
        ->name('virtual-accounts.dynamic.index');
    Route::get('/virtual-accounts/fixed/create', [FixedVirtualAccountController::class, 'create'])
        ->name('virtual-accounts.fixed.create');
    Route::post('/virtual-accounts/fixed', [FixedVirtualAccountController::class, 'store'])
        ->name('virtual-accounts.fixed.store');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
