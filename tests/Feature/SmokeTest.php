<?php

use App\Http\Controllers\SettingsController;
use App\Models;
use App\Reports\ReportRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Spatie\Permission\Models\Role;

/**
 * Visits every page with the full demo dataset loaded, as the owner and as a
 * cashier, to catch runtime errors and SQL dialect issues (run it against
 * MySQL as well as SQLite).
 */
it('renders every page with demo data', function () {
    $this->seed(DatabaseSeeder::class);

    $skip = ['logout', 'lock', 'up', 'storage.', 'livewire.', 'impersonate.', 'files.show', 'backups.download', 'notifications.open', 'password.reset', 'receipts.verify'];
    $models = [
        'adjustment' => Models\StockAdjustment::class, 'branch' => Models\Branch::class, 'customerPayment' => Models\CustomerPayment::class,
        'customer' => Models\Customer::class, 'expense' => Models\Expense::class, 'goodsReceipt' => Models\GoodsReceipt::class,
        'product' => Models\Product::class, 'purchaseOrder' => Models\PurchaseOrder::class, 'purchaseReturn' => Models\PurchaseReturn::class,
        'sale' => Models\Sale::class, 'return' => Models\SaleReturn::class, 'shift' => Models\Shift::class, 'stockTake' => Models\StockTake::class,
        'supplierBill' => Models\SupplierBill::class, 'supplier' => Models\Supplier::class, 'transfer' => Models\StockTransfer::class,
        'user' => Models\User::class, 'role' => Role::class,
    ];

    $urls = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $r) => in_array('GET', $r->methods()) && $r->getName())
        ->reject(fn (Route $r) => collect($skip)->contains(fn ($s) => str_starts_with($r->getName(), $s)))
        ->reject(fn (Route $r) => in_array('guest', $r->gatherMiddleware()))
        ->flatMap(function (Route $r) use ($models) {
            $uri = '/'.ltrim($r->uri(), '/');
            if ($r->getName() === 'quotations.show' || $r->getName() === 'quotations.edit') {
                $id = Models\Sale::withoutGlobalScopes()->where('status', 'quotation')->value('id');

                return $id ? [str_replace('{quotation}', $id, $uri)] : [];
            }
            if ($r->getName() === 'reports.show') {
                return ReportRegistry::all()->keys()->map(fn ($k) => "/reports/$k")->all();
            }
            if ($r->getName() === 'settings.edit') {
                return collect(array_keys(SettingsController::GROUPS))->map(fn ($g) => "/settings/$g")->all();
            }
            if ($r->getName() === 'shifts.report') {
                $id = Models\Shift::withoutGlobalScopes()->value('id');

                return ["/shifts/$id/report/x", "/shifts/$id/report/z"];
            }
            preg_match_all('/\{(\w+)\??\}/', $uri, $m);
            foreach ($m[1] as $param) {
                if (! isset($models[$param])) {
                    return [];
                }
                $id = $models[$param]::query()->withoutGlobalScopes()->value('id');
                if (! $id) {
                    return [];
                }
                $uri = preg_replace('/\{'.$param.'\??\}/', (string) $id, $uri);
            }

            return [$uri];
        })->unique()->values();

    foreach (['owner@dukapos.test', 'cashier@dukapos.test'] as $email) {
        $this->actingAs(Models\User::where('email', $email)->firstOrFail());
        foreach ($urls as $url) {
            $status = $this->get($url)->baseResponse->getStatusCode();
            expect($status)->toBeLessThan(500, "$url as $email returned $status");
        }
    }
    expect($urls->count())->toBeGreaterThan(80);
});
