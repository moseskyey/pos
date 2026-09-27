<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BranchPolicy extends BasePolicy
{
    protected string $view = 'branches.view';

    protected string $manage = 'branches.manage';

    public function view(User $user, Model $branch): bool
    {
        /** @var Branch $branch */
        return $user->can('branches.view') && ($user->can('branches.view_all') || $user->branches->contains($branch->id));
    }

    public function update(User $user, Model $branch): bool
    {
        return $user->can('branches.manage') && $this->view($user, $branch);
    }

    public function delete(User $user, Model $branch): bool
    {
        return $this->update($user, $branch);
    }
}
