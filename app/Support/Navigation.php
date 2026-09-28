<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Sidebar navigation definition: [label, icon, route, permission(s), active pattern(s), optional feature].
 * Items are filtered by permission and by switched-off features at render time.
 */
class Navigation
{
    public static function groups(): array
    {
        return [
            [null, [
                ['Dashboard', 'bi-grid-1x2', 'dashboard', 'dashboard.view', 'dashboard'],
                ['POS', 'bi-upc-scan', 'pos', 'pos.access', 'pos*'],
            ]],
            ['Sales', [
                ['Sales', 'bi-receipt', 'sales.index', ['sales.view', 'sales.view_all'], 'sales.*'],
                ['Returns', 'bi-arrow-counterclockwise', 'returns.index', 'sales.return', 'returns.*'],
                ['Quotations', 'bi-file-earmark-text', 'quotations.index', 'quotations.manage', 'quotations.*', 'quotations'],
                ['Promotions', 'bi-megaphone', 'promotions.index', 'promotions.manage', 'promotions.*', 'promotions'],
                ['Shifts', 'bi-cash-coin', 'shifts.index', ['shifts.open', 'shifts.manage'], 'shifts.*'],
            ]],
            ['Products', [
                ['Products', 'bi-box-seam', 'products.index', 'products.view', 'products.*'],
                ['Categories', 'bi-tags', 'categories.index', 'catalog.manage', 'categories.*'],
                ['Brands', 'bi-award', 'brands.index', 'catalog.manage', 'brands.*'],
                ['Units', 'bi-rulers', 'units.index', 'catalog.manage', 'units.*'],
                ['Barcode Labels', 'bi-upc', 'labels.index', 'products.labels', 'labels.*'],
            ]],
            ['Inventory', [
                ['Stock Levels', 'bi-stack', 'stock.index', 'stock.view', 'stock.index'],
                ['Movements', 'bi-arrow-left-right', 'stock.movements', 'stock.view', 'stock.movements'],
                ['Adjustments', 'bi-sliders', 'adjustments.index', ['stock.adjust', 'stock.adjust.approve'], 'adjustments.*'],
                ['Transfers', 'bi-truck', 'transfers.index', ['stock.transfer', 'stock.transfer.approve'], 'transfers.*', 'transfers'],
                ['Stock Takes', 'bi-clipboard-check', 'stock-takes.index', ['stock.take', 'stock.take.approve'], 'stock-takes.*', 'stock_takes'],
                ['Batches & Expiry', 'bi-calendar2-x', 'batches.index', 'stock.view', 'batches.*', 'batches'],
                ['Serial Numbers', 'bi-upc-scan', 'serials.index', ['stock.view', 'sales.view', 'sales.view_all'], 'serials.*', 'serials'],
            ]],
            ['Purchases', [
                ['Suppliers', 'bi-building', 'suppliers.index', 'suppliers.view', 'suppliers.*'],
                ['Purchase Orders', 'bi-cart-check', 'purchase-orders.index', 'purchases.view', 'purchase-orders.*'],
                ['Goods Received', 'bi-box-arrow-in-down', 'goods-receipts.index', ['purchases.view', 'purchases.receive'], 'goods-receipts.*'],
                ['Supplier Bills', 'bi-journal-text', 'supplier-bills.index', 'supplier.payments', 'supplier-bills.*'],
                ['Purchase Returns', 'bi-box-arrow-up', 'purchase-returns.index', 'purchases.return', 'purchase-returns.*'],
                ['Reorder', 'bi-lightning-charge', 'reorder.index', 'purchases.manage', 'reorder.*'],
            ]],
            ['Customers', [
                ['Customers', 'bi-people', 'customers.index', 'customers.view', 'customers.*'],
                ['Payments', 'bi-wallet2', 'customer-payments.index', 'customers.payments', 'customer-payments.*'],
                ['Gift Cards', 'bi-gift', 'gift-cards.index', 'gift_cards.manage', 'gift-cards.*', 'gift_cards'],
                ['Cheques', 'bi-bank2', 'cheques.index', 'cheques.manage', 'cheques.*', 'cheques'],
            ]],
            ['Expenses', [
                ['Expenses', 'bi-credit-card-2-back', 'expenses.index', 'expenses.view', 'expenses.*', 'expenses'],
                ['Recurring', 'bi-arrow-repeat', 'recurring-expenses.index', 'expenses.manage', 'recurring-expenses.*', 'expenses'],
            ]],
            ['Reports', [
                ['Reports', 'bi-bar-chart-line', 'reports.index', 'reports.view', 'reports.*'],
            ]],
            ['Settings', [
                ['Settings', 'bi-gear', 'settings.edit', 'settings.manage', 'settings.*'],
                ['Subscription', 'bi-credit-card-2-front', 'billing.index', 'settings.manage', 'billing.*'],
                ['Branches', 'bi-shop', 'branches.index', 'branches.view', ['branches.*', 'registers.*']],
                ['Users', 'bi-person-badge', 'users.index', 'users.view', 'users.*'],
                ['Roles', 'bi-shield-lock', 'roles.index', 'roles.manage', 'roles.*'],
                ['Activity Log', 'bi-clock-history', 'activity.index', 'activity.view', 'activity.*'],
                ['Backups', 'bi-cloud-arrow-down', 'backups.index', 'backups.manage', 'backups.*'],
            ]],
        ];
    }

    /** Groups visible to the current user, with only routes that exist. */
    public static function visible(): array
    {
        $user = auth()->user();
        $out = [];
        foreach (static::groups() as [$heading, $items]) {
            $visible = [];
            foreach ($items as $item) {
                [$label, $icon, $route, $permission, $active] = $item;
                if (! Route::has($route) || (isset($item[5]) && Features::disabled($item[5]))) {
                    continue;
                }
                $permissions = (array) $permission;
                if (! $user || ! collect($permissions)->contains(fn ($p) => $user->can($p))) {
                    continue;
                }
                $visible[] = [
                    'label' => __($label),
                    'icon' => $icon,
                    'url' => route($route),
                    'active' => request()->routeIs(...(array) $active),
                ];
            }
            if ($visible) {
                $out[] = ['heading' => $heading ? __($heading) : null, 'items' => $visible];
            }
        }

        return $out;
    }
}
