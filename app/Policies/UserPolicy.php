<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    protected string $view = 'users.view';

    protected string $manage = 'users.manage';

    public function view(User $user, Model $model): bool
    {
        return $user->is($model) || $user->can('users.view');
    }

    public function update(User $user, Model $model): bool
    {
        /** @var User $model */
        if ($model->hasRole('owner') && ! $user->hasRole('owner')) {
            return false;
        }

        return $user->can('users.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model) && ! $user->is($model);
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->can('users.impersonate') && ! $user->is($target) && ! $target->hasRole('owner') && $target->is_active;
    }
}
