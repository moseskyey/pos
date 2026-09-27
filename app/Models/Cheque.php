<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A cheque received from a customer or issued to a supplier, tracked from
 * pending (possibly post-dated) until it clears, bounces or is cancelled.
 */
class Cheque extends Model
{
    use BelongsToBranch;

    public const STATUSES = ['pending', 'cleared', 'bounced', 'cancelled'];

    protected $fillable = ['branch_id', 'direction', 'number', 'bank', 'cheque_date', 'amount', 'status', 'customer_id', 'supplier_id',
        'payable_type', 'payable_id', 'status_at', 'status_by', 'note'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['cheque_date' => 'date', 'amount' => 'decimal:2', 'status_at' => 'datetime'];
    }

    public function isPostDated(): bool
    {
        return $this->cheque_date->gt(today());
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_by');
    }
}
