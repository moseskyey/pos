<?php

namespace App\Models;

use App\Support\BranchContext;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const STATUSES = ['requested', 'approved', 'dispatched', 'received', 'cancelled'];

    protected $fillable = [
        'number', 'from_branch_id', 'to_branch_id', 'status', 'note', 'requested_by', 'approved_by', 'approved_at',
        'dispatched_by', 'dispatched_at', 'received_by', 'received_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime'];
    }

    /** Transfers are visible to both the sending and receiving branch. */
    protected static function booted(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            $context = app(BranchContext::class);
            if (! $context->scopingEnabled()) {
                return;
            }
            $ids = $context->activeIds();
            $builder->where(fn ($q) => $q->whereIn('from_branch_id', $ids)->orWhereIn('to_branch_id', $ids));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id')->withTrashed();
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id')->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by')->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function hasDiscrepancy(): bool
    {
        return $this->status === 'received' && $this->items->contains(fn ($i) => Qty::cmp($i->quantity_dispatched, $i->quantity_received) !== 0);
    }
}
