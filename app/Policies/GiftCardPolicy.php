<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class GiftCardPolicy extends BasePolicy
{
    protected string $view = 'gift_cards.manage';

    protected string $manage = 'gift_cards.manage';

    /** A card can be spent at any branch, so any branch's staff may look it up. */
    protected function inBranch(Model $model): bool
    {
        return true;
    }
}
