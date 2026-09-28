<?php

namespace App\Policies;

class ChequePolicy extends BasePolicy
{
    protected string $view = 'cheques.manage';

    protected string $manage = 'cheques.manage';
}
