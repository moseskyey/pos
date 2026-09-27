<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $fillable = ['customer_id', 'sale_id', 'type', 'points', 'balance_after', 'note'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withoutGlobalScopes();
    }
}
