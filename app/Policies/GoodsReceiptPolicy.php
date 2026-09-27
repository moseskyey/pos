<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptPolicy extends BasePolicy
{
    protected string $view = 'purchases.view';

    protected string $manage = 'purchases.receive';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['purchases.view', 'purchases.receive']);
    }

    public function view(User $user, Model $receipt): bool
    {
        return $this->viewAny($user) && $this->inBranch($receipt);
    }
}
