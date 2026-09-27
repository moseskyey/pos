<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicy extends BasePolicy
{
    protected string $view = 'shifts.manage';

    protected string $manage = 'shifts.manage';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['shifts.open', 'shifts.manage']);
    }

    public function view(User $user, Model $shift): bool
    {
        /** @var Shift $shift */
        return ($shift->user_id === $user->id || $user->can('shifts.manage')) && $this->inBranch($shift);
    }

    /** Cash in / cash out on the cashier's own open shift (or any, for managers). */
    public function cashMovement(User $user, Model $shift): bool
    {
        /** @var Shift $shift */
        return $user->can('cash.movements') && ($shift->user_id === $user->id || $user->can('shifts.manage')) && $this->inBranch($shift);
    }

    /** The cashier closes their own shift; a manager can force-close any shift. */
    public function close(User $user, Model $shift): bool
    {
        /** @var Shift $shift */
        return (($shift->user_id === $user->id && $user->can('shifts.open')) || $user->can('shifts.manage')) && $this->inBranch($shift);
    }
}
