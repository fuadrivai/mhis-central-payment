<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedVirtualAccountController;
use App\Http\Controllers\PasswordController;
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
    Route::get('/virtual-accounts/fixed', [FixedVirtualAccountController::class, 'index'])
        ->name('virtual-accounts.fixed.index');
    Route::get('/virtual-accounts/fixed/create', [FixedVirtualAccountController::class, 'create'])
        ->name('virtual-accounts.fixed.create');
    Route::post('/virtual-accounts/fixed', [FixedVirtualAccountController::class, 'store'])
        ->name('virtual-accounts.fixed.store');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
