<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\Branch;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class AlertService
{
    /** Users who should receive alerts for a branch and permission. */
    public function recipients(?int $branchId, string $permission): Collection
    {
        return User::query()->where('is_active', true)->with(['roles.permissions', 'permissions', 'branches'])->get()
            ->filter(fn (User $u) => $u->can($permission) && ($branchId === null || $u->can('branches.view_all') || $u->branches->contains('id', $branchId)))
            ->values();
    }

    public function notify(?int $branchId, string $permission, SystemAlert $alert, ?User $except = null): void
    {
        $users = $this->recipients($branchId, $permission)->reject(fn ($u) => $except && $u->is($except));
        if ($users->isNotEmpty()) {
            Notification::send($users, $alert);
        }
    }

    /** Daily low-stock & expiry digest per branch. Returns number of alerts sent. */
    public function dailyStockAlerts(): int
    {
        $sent = 0;
        foreach (Branch::query()->where('is_active', true)->get() as $branch) {
            $low = ProductStock::withoutGlobalScopes()->where('product_stocks.branch_id', $branch->id)
                ->join('products', 'products.id', '=', 'product_stocks.product_id')
                ->whereNull('products.deleted_at')->where('products.is_active', true)->where('products.track_stock', true)
                ->whereColumn('product_stocks.quantity', '<=', 'products.reorder_level')
                ->orderBy('product_stocks.quantity')->get(['products.name', 'product_stocks.quantity']);

            if ($low->isNotEmpty()) {
                $names = $low->take(5)->map(fn ($r) => $r->name.' ('.qty($r->quantity).')')->join(', ');
                $alert = new SystemAlert(
                    __(':count low-stock items at :branch', ['count' => $low->count(), 'branch' => $branch->name]),
                    $names.($low->count() > 5 ? '…' : ''),
                    route('stock.index', ['filters' => ['status' => 'low']]),
                    'bi-exclamation-triangle', 'warning', (bool) setting('notify.low_stock_email'),
                );
                $this->notify($branch->id, 'stock.view', $alert);
                if (setting('notify.low_stock_sms')) {
                    foreach ($this->recipients($branch->id, 'stock.adjust.approve')->whereNotNull('phone') as $manager) {
                        app(SmsGateway::class)->send($manager->phone, $alert->title.': '.$alert->message);
                    }
                }
                $sent++;
            }

            $days = (int) setting('inventory.expiry_alert_days', 30);
            $expiring = ProductBatch::withoutGlobalScopes()->with('product')->where('branch_id', $branch->id)->where('quantity', '>', 0)
                ->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays($days))->orderBy('expiry_date')->get();
            if ($expiring->isNotEmpty()) {
                $this->notify($branch->id, 'stock.view', new SystemAlert(
                    __(':count batches expiring within :days days at :branch', ['count' => $expiring->count(), 'days' => $days, 'branch' => $branch->name]),
                    $expiring->take(5)->map(fn ($b) => $b->product?->name.' '.format_date($b->expiry_date))->join(', '),
                    route('batches.index'), 'bi-calendar2-x', 'danger',
                ));
                $sent++;
            }
        }

        return $sent;
    }
}
