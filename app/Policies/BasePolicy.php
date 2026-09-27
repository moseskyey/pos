<?php

namespace App\Policies;

use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission-prefix policy. Subclasses set $view / $manage permission names;
 * branch-owned models are additionally checked against the user's branches.
 */
abstract class BasePolicy
{
    protected string $view = '';

    protected string $manage = '';

    protected ?string $delete = null;

    public function viewAny(User $user): bool
    {
        return $user->can($this->view);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->view) && $this->inBranch($model);
    }

    public function create(User $user): bool
    {
        return $user->can($this->manage);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->manage) && $this->inBranch($model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->delete ?? $this->manage) && $this->inBranch($model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    protected function inBranch(Model $model): bool
    {
        if (! array_key_exists('branch_id', $model->getAttributes())) {
            return true;
        }

        return app(BranchContext::class)->canAccess($model->branch_id);
    }
}
