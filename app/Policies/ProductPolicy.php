<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductPolicy extends BasePolicy
{
    protected string $view = 'products.view';

    protected string $manage = 'products.edit';

    protected ?string $delete = 'products.delete';

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('products.view');
    }
}
