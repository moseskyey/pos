<?php

namespace App\Services\Platform;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;

/**
 * Enforces the business's plan limits (branches, active users, products).
 * No plan, or an empty limit, means unlimited.
 */
class PlanLimits
{
    public static function usage(): array
    {
        return [
            'branches' => Branch::withoutGlobalScopes()->count(),
            'users' => User::where('is_active', true)->count(),
            'products' => Product::withoutGlobalScopes()->whereNull('parent_id')->count(),
        ];
    }

    public static function limit(string $resource): ?int
    {
        return tenant()?->plan?->limit($resource);
    }

    public static function ensureCanAdd(string $resource, int $adding = 1): void
    {
        $limit = static::limit($resource);
        if ($limit === null) {
            return;
        }

        $current = static::usage()[$resource];
        if ($current + $adding > $limit) {
            $allowance = match ($resource) {
                'branches' => trans_choice(':count branch|:count branches', $limit),
                'users' => trans_choice(':count active user|:count active users', $limit),
                default => trans_choice(':count product|:count products', $limit),
            };

            throw new BusinessRuleException(__('Your :plan plan allows up to :allowance. Upgrade your plan to add more.', [
                'plan' => tenant()->plan->name,
                'allowance' => str_replace((string) $limit, number_format($limit), $allowance),
            ]));
        }
    }

    /** Hook the limits into the models, so every way of creating records is covered. */
    public static function register(): void
    {
        Branch::creating(fn () => static::ensureCanAdd('branches'));
        Product::creating(fn (Product $product) => $product->parent_id ? null : static::ensureCanAdd('products'));
        User::creating(fn (User $user) => $user->is_active === false ? null : static::ensureCanAdd('users'));
        User::updating(fn (User $user) => $user->isDirty('is_active') && $user->is_active ? static::ensureCanAdd('users') : null);
    }
}
