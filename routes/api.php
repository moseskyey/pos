<?php

use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

// Payment gateway callbacks (signature verified, idempotent).
Route::post('/payments/callback/{gateway}', PaymentCallbackController::class)
    ->middleware('throttle:callbacks')
    ->name('payments.callback');
