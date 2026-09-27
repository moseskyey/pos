<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use App\Enums\AdjustmentReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'number', 'reason', 'status', 'note', 'created_by', 'approved_by', 'approved_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['reason' => AdjustmentReason::class, 'approved_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
