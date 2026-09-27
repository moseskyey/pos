<?php

namespace App\Concerns;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global scope limiting branch-owned records to the branches the signed-in
 * user can see (and to the branch selected in the switcher).
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            $context = app(BranchContext::class);
            if (! $context->scopingEnabled()) {
                return;
            }
            $builder->whereIn($builder->getModel()->qualifyColumn('branch_id'), $context->activeIds());
        });

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = app(BranchContext::class)->currentId();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId ? $query->where($this->qualifyColumn('branch_id'), $branchId) : $query;
    }
}
