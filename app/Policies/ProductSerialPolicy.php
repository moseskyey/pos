<?php

namespace App\Policies;

use App\Models\User;

/** Anyone who sells or keeps stock can look up a serial; registering stock needs stock.adjust. */
class ProductSerialPolicy extends BasePolicy
{
    protected string $view = 'stock.view';

    protected string $manage = 'stock.adjust';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['stock.view', 'sales.view', 'sales.view_all']);
    }
}
