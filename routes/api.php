<?php

use App\Http\Controllers\Api\FixedVirtualAccountController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MandiriPaymentCallbackController;
use App\Http\Middleware\VerifyMandiriCallbackApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['branch.api', 'throttle:branch-api'])->group(function (): void {
    Route::post('/fixed/va/create', [FixedVirtualAccountController::class, 'store'])
        ->name('api.v1.virtual-accounts.fixed.store');
    Route::post('/fixed/va/void', [FixedVirtualAccountController::class, 'void'])
        ->name('api.v1.virtual-accounts.fixed.void');

    Route::post('/invoice/create', [InvoiceController::class, 'store'])
        ->name('api.v1.invoices.store');
});

Route::post('/v1/callbacks/va', [MandiriPaymentCallbackController::class, 'store'])
    ->middleware(['throttle:120,1', VerifyMandiriCallbackApiKey::class])
    ->name('api.v1.callbacks.mandiri.virtual-account-paid');
