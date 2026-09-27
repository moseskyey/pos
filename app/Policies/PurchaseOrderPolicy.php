<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderPolicy extends BasePolicy
{
    protected string $view = 'purchases.view';

    protected string $manage = 'purchases.manage';

    public function update(User $user, Model $order): bool
    {
        /** @var PurchaseOrder $order */
        return parent::update($user, $order) && $order->status === 'draft';
    }

    /** Send, email or cancel. */
    public function process(User $user, Model $order): bool
    {
        return parent::update($user, $order);
    }

    public function reorder(User $user): bool
    {
        return $user->can('purchases.manage');
    }
}
