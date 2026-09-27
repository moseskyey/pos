<?php

use App\Http\Controllers\Api\V1 as ApiV1;
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

// Public REST API for integrations (Settings → Features → API access).
Route::prefix('v1')->middleware(['throttle:api-tokens', 'api.token'])->name('api.v1.')->group(function () {
    Route::get('/me', [ApiV1\ApiController::class, 'me'])->name('me');
    Route::get('/products', [ApiV1\ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ApiV1\ProductController::class, 'show'])->name('products.show');
    Route::get('/customers', [ApiV1\CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [ApiV1\CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [ApiV1\CustomerController::class, 'show'])->name('customers.show');
    Route::get('/sales', [ApiV1\SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [ApiV1\SaleController::class, 'show'])->name('sales.show');
    Route::get('/reports/summary', [ApiV1\SaleController::class, 'summary'])->name('reports.summary');
});
