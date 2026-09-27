<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy extends BasePolicy
{
    protected string $view = 'customers.view';

    protected string $manage = 'customers.manage';

    /** Receive payments, send reminders and share statements. */
    public function collect(User $user): bool
    {
        return $user->can('customers.payments');
    }
}
