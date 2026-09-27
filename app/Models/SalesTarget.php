<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A month's sales target for one salesperson, or for the whole branch when user_id is null. */
class SalesTarget extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'user_id', 'month', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
