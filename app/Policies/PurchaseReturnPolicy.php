<?php

namespace App\Policies;

class PurchaseReturnPolicy extends BasePolicy
{
    protected string $view = 'purchases.return';

    protected string $manage = 'purchases.return';
}
