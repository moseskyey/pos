<?php

use App\Http\Controllers\BillingCallbackController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

// A business's own payment gateway callbacks (signature verified, idempotent).
Route::middleware(['tenant.route', 'throttle:callbacks'])->group(function () {
    Route::post('/payments/callback/{gateway}/{tenant}', PaymentCallbackController::class)
        ->whereNumber('tenant')
        ->name('payments.callback');
    // Payments started before multi-tenancy (the adopted business).
    Route::post('/payments/callback/{gateway}', PaymentCallbackController::class)
        ->name('payments.callback.legacy');
});

// Subscription payments to the platform (central database).
Route::post('/billing/callback/fastlipa', BillingCallbackController::class)
    ->middleware('throttle:callbacks')
    ->name('billing.callback');
