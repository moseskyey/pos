<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentPolicy extends BasePolicy
{
    protected string $view = 'stock.adjust';

    protected string $manage = 'stock.adjust';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['stock.adjust', 'stock.adjust.approve']);
    }

    public function view(User $user, Model $adjustment): bool
    {
        return $user->canAny(['stock.adjust', 'stock.adjust.approve', 'stock.view']) && $this->inBranch($adjustment);
    }

    /** Approve or reject. */
    public function approve(User $user, Model $adjustment): bool
    {
        return $user->can('stock.adjust.approve') && $this->inBranch($adjustment);
    }
}
