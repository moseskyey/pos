<?php

namespace App\Policies;

use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SaleReturnPolicy extends BasePolicy
{
    protected string $view = 'sales.return';

    protected string $manage = 'sales.return';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['sales.return', 'sales.view_all']);
    }

    /** Cashiers start returns from the POS; the service asks for a manager PIN when needed. */
    public function create(User $user): bool
    {
        return $user->canAny(['sales.return', 'pos.access']);
    }

    public function view(User $user, Model $return): bool
    {
        /** @var SaleReturn $return */
        return ($user->canAny(['sales.return', 'sales.view_all']) || $return->user_id === $user->id) && $this->inBranch($return);
    }
}
