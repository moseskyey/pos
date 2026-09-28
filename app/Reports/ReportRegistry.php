<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Collection;

class ReportRegistry
{
    /** @var array<int, class-string<Report>> */
    public const REPORTS = [
        Sales\SalesSummaryReport::class,
        Sales\SalesByProductReport::class,
        Sales\SalesByDimensionReport::class,
        Sales\SalesByHourReport::class,
        Sales\DiscountsReport::class,
        Sales\ReturnsVoidsReport::class,
        Sales\ShiftReconciliationReport::class,
        Sales\CommissionReport::class,
        Finance\ProfitLossReport::class,
        Finance\GrossProfitReport::class,
        Finance\VatReport::class,
        Finance\ExpensesReport::class,
        Finance\DebtorsAgingReport::class,
        Inventory\StockValuationReport::class,
        Inventory\StockMovementReport::class,
        Inventory\LowStockReport::class,
        Inventory\ExpiryReport::class,
        Purchases\PurchasesBySupplierReport::class,
        Purchases\SupplierAgingReport::class,
    ];

    /** @return Collection<string, Report> */
    public static function all(): Collection
    {
        return collect(self::REPORTS)->mapWithKeys(fn ($class) => [$class::key() => app($class)]);
    }

    public static function find(string $key): ?Report
    {
        return static::all()->get($key);
    }

    /** @return Collection<string, Collection<string, Report>> */
    public static function forUser(User $user): Collection
    {
        return static::all()->filter(fn (Report $r) => $r->authorize($user))->groupBy(fn (Report $r) => $r->group(), true);
    }
}
