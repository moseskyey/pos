<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class SubscriptionCallback extends Model
{
    protected $connection = 'central';

    protected $fillable = ['gateway', 'subscription_payment_id', 'payload', 'ip', 'result'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
