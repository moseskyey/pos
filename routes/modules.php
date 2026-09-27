<?php

use App\Http\Controllers\BulkPriceController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\StockTransferController;
use Illuminate\Support\Facades\Route;

/*
| Feature module routes (catalog, inventory, POS, sales, purchases, reports…).
| Loaded inside the authenticated route group in web.php.
*/

// ---------------------------------------------------------------- Catalog --
Route::view('/categories', 'categories.index')->middleware('can:catalog.manage')->name('categories.index');
Route::view('/brands', 'brands.index')->middleware('can:catalog.manage')->name('brands.index');
Route::view('/units', 'units.index')->middleware('can:catalog.manage')->name('units.index');

Route::get('/products/import', [ProductImportController::class, 'create'])->name('products.import');
Route::post('/products/import', [ProductImportController::class, 'upload'])->name('products.import.upload');
Route::post('/products/import/commit', [ProductImportController::class, 'commit'])->name('products.import.commit');
Route::post('/products/import/cancel', [ProductImportController::class, 'cancel'])->name('products.import.cancel');
Route::get('/products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
Route::get('/products/export', [ProductImportController::class, 'export'])->name('products.export');
Route::get('/products/bulk-price', [BulkPriceController::class, 'create'])->name('products.bulk-price');
Route::post('/products/bulk-price', [BulkPriceController::class, 'store'])->name('products.bulk-price.store');
Route::resource('products', ProductController::class);

Route::get('/labels', [LabelController::class, 'index'])->name('labels.index');
Route::post('/labels/print', [LabelController::class, 'print'])->name('labels.print');

// -------------------------------------------------------------- Inventory --
Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
Route::get('/stock/movements', [StockController::class, 'movements'])->name('stock.movements');
Route::get('/stock/batches', [StockController::class, 'batches'])->name('batches.index');

Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->name('adjustments.index');
Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
Route::get('/adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->name('adjustments.show');
Route::post('/adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('adjustments.approve');
Route::post('/adjustments/{adjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('adjustments.reject');

Route::get('/transfers', [StockTransferController::class, 'index'])->name('transfers.index');
Route::get('/transfers/create', [StockTransferController::class, 'create'])->name('transfers.create');
Route::get('/transfers/{transfer}', [StockTransferController::class, 'show'])->name('transfers.show');
Route::post('/transfers/{transfer}/approve', [StockTransferController::class, 'approve'])->name('transfers.approve');
Route::post('/transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('transfers.dispatch');
Route::post('/transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');
Route::post('/transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');

Route::get('/stock-takes', [StockTakeController::class, 'index'])->name('stock-takes.index');
Route::post('/stock-takes', [StockTakeController::class, 'store'])->name('stock-takes.store');
Route::get('/stock-takes/{stockTake}', [StockTakeController::class, 'show'])->name('stock-takes.show');
Route::post('/stock-takes/{stockTake}/submit', [StockTakeController::class, 'submit'])->name('stock-takes.submit');
Route::post('/stock-takes/{stockTake}/post', [StockTakeController::class, 'post'])->name('stock-takes.post');
Route::post('/stock-takes/{stockTake}/cancel', [StockTakeController::class, 'cancel'])->name('stock-takes.cancel');
