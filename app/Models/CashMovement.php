<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'shift_id', 'user_id', 'type', 'amount', 'reason', 'reference_type', 'reference_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withoutGlobalScopes();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
