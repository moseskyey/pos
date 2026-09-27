<?php

namespace App\Policies;

class ExpensePolicy extends BasePolicy
{
    protected string $view = 'expenses.view';

    protected string $manage = 'expenses.manage';
}
