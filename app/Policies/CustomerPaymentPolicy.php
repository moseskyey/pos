<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentPolicy extends BasePolicy
{
    protected string $view = 'customers.payments';

    protected string $manage = 'customers.payments';

    public function view(User $user, Model $payment): bool
    {
        return $user->canAny(['customers.payments', 'customers.view']) && $this->inBranch($payment);
    }
}
