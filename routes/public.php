<?php

use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ShareController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
| Public pages reached from receipts and WhatsApp links. There is no session:
| the business comes from the URL, and every link is signed.
| Loaded with the "tenant.route" middleware (bootstrap/app.php).
*/

Route::middleware([SubstituteBindings::class, 'throttle:30,1'])->group(function () {
    Route::prefix('t/{tenant}')->whereNumber('tenant')->group(function () {
        Route::get('/verify/{number}', [ReceiptController::class, 'verify'])->name('receipts.verify');
        Route::middleware('signed')->group(function () {
            Route::get('/share/invoice/{sale}', [ShareController::class, 'invoice'])->whereNumber('sale')->name('share.invoice');
            Route::get('/share/statement/{customer}', [ShareController::class, 'statement'])->name('share.statement');
        });
    });

    // Receipts printed and links sent before multi-tenancy (the adopted business).
    Route::get('/verify/{number}', [ReceiptController::class, 'verify'])->name('receipts.verify.legacy');
    Route::middleware('signed')->group(function () {
        Route::get('/share/invoice/{sale}', [ShareController::class, 'invoice'])->whereNumber('sale')->name('share.invoice.legacy');
        Route::get('/share/statement/{customer}', [ShareController::class, 'statement'])->name('share.statement.legacy');
    });
});
