<?php

namespace App\Policies;

use App\Models\User;

class SupplierBillPolicy extends BasePolicy
{
    protected string $view = 'supplier.payments';

    protected string $manage = 'supplier.payments';

    public function pay(User $user): bool
    {
        return $user->can('supplier.payments');
    }
}
