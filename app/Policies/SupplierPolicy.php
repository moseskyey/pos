<?php

namespace App\Policies;

class SupplierPolicy extends BasePolicy
{
    protected string $view = 'suppliers.view';

    protected string $manage = 'suppliers.manage';
}
