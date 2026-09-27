<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockTakePolicy extends BasePolicy
{
    protected string $view = 'stock.take';

    protected string $manage = 'stock.take';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['stock.take', 'stock.take.approve']);
    }

    public function view(User $user, Model $take): bool
    {
        return $this->viewAny($user) && $this->inBranch($take);
    }

    /** Post the counted variances to stock. */
    public function approve(User $user, Model $take): bool
    {
        return $user->can('stock.take.approve') && $this->inBranch($take);
    }

    public function cancel(User $user, Model $take): bool
    {
        return $this->view($user, $take);
    }
}
