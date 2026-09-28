<?php

namespace App\Policies;

class PromotionPolicy extends BasePolicy
{
    protected string $view = 'promotions.manage';

    protected string $manage = 'promotions.manage';
}
