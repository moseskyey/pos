<?php

use App\Http\Controllers\BulkPriceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
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

// ------------------------------------------------------------ POS & shifts --
Route::view('/pos', 'pos.index')->middleware('can:pos.access')->name('pos');
Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
Route::get('/shifts/current', [ShiftController::class, 'current'])->name('shifts.current');
Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
Route::post('/shifts/{shift}/cash', [ShiftController::class, 'cash'])->name('shifts.cash');
Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
Route::get('/shifts/{shift}/report/{type}', [ShiftController::class, 'report'])->name('shifts.report');

Route::get('/receipts/{sale}', [ReceiptController::class, 'show'])->name('receipts.show');
Route::get('/receipts/{sale}/reprint', [ReceiptController::class, 'reprint'])->name('receipts.reprint');
Route::get('/receipts/{sale}/invoice', [ReceiptController::class, 'invoice'])->name('receipts.invoice');
Route::get('/receipts/{sale}/delivery-note', [ReceiptController::class, 'deliveryNote'])->name('receipts.delivery-note');

// ---------------------------------------------------- Sales & customers --
Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
Route::get('/returns', [SaleReturnController::class, 'index'])->name('returns.index');
Route::get('/returns/create', [SaleReturnController::class, 'create'])->name('returns.create');
Route::get('/returns/{return}', [SaleReturnController::class, 'show'])->name('returns.show');
Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');

Route::resource('customers', CustomerController::class);
Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
Route::post('/customers/{customer}/remind', [CustomerController::class, 'remind'])->name('customers.remind');
Route::get('/customer-payments', [CustomerPaymentController::class, 'index'])->name('customer-payments.index');
Route::get('/customer-payments/create', [CustomerPaymentController::class, 'create'])->name('customer-payments.create');
Route::post('/customer-payments', [CustomerPaymentController::class, 'store'])->name('customer-payments.store');
Route::get('/customer-payments/{customerPayment}', [CustomerPaymentController::class, 'show'])->name('customer-payments.show');

// -------------------------------------------------- Purchases & expenses --
Route::resource('suppliers', SupplierController::class);
Route::get('/purchase-orders', [PurchaseController::class, 'orders'])->name('purchase-orders.index');
Route::get('/purchase-orders/create', [PurchaseController::class, 'createOrder'])->name('purchase-orders.create');
Route::get('/purchase-orders/{purchaseOrder}', [PurchaseController::class, 'showOrder'])->name('purchase-orders.show');
Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseController::class, 'editOrder'])->name('purchase-orders.edit');
Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseController::class, 'orderPdf'])->name('purchase-orders.pdf');
Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseController::class, 'sendOrder'])->name('purchase-orders.send');
Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseController::class, 'cancelOrder'])->name('purchase-orders.cancel');
Route::get('/goods-receipts', [PurchaseController::class, 'receipts'])->name('goods-receipts.index');
Route::get('/goods-receipts/create', [PurchaseController::class, 'createReceipt'])->name('goods-receipts.create');
Route::get('/goods-receipts/{goodsReceipt}', [PurchaseController::class, 'showReceipt'])->name('goods-receipts.show');
Route::get('/supplier-bills', [PurchaseController::class, 'bills'])->name('supplier-bills.index');
Route::post('/supplier-bills', [PurchaseController::class, 'storeBill'])->name('supplier-bills.store');
Route::get('/supplier-bills/{supplierBill}', [PurchaseController::class, 'showBill'])->name('supplier-bills.show');
Route::get('/supplier-payments/create', [PurchaseController::class, 'createPayment'])->name('supplier-payments.create');
Route::post('/supplier-payments', [PurchaseController::class, 'storePayment'])->name('supplier-payments.store');
Route::get('/purchase-returns', [PurchaseController::class, 'returns'])->name('purchase-returns.index');
Route::get('/purchase-returns/create', [PurchaseController::class, 'createReturn'])->name('purchase-returns.create');
Route::get('/purchase-returns/{purchaseReturn}', [PurchaseController::class, 'showReturn'])->name('purchase-returns.show');
Route::get('/reorder', [PurchaseController::class, 'reorder'])->name('reorder.index');
Route::post('/reorder', [PurchaseController::class, 'createFromReorder'])->name('reorder.store');

Route::get('/expenses/recurring', [ExpenseController::class, 'recurring'])->name('recurring-expenses.index');
Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
Route::resource('expenses', ExpenseController::class)->except('show');
