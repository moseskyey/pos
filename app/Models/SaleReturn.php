<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'sale_id', 'customer_id', 'shift_id', 'user_id', 'approved_by', 'number', 'reason',
        'refund_method', 'refund_reference', 'subtotal', 'tax_total', 'refund_total', 'cost_total',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'refund_total' => 'decimal:2', 'cost_total' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withoutGlobalScopes();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function refundLabel(): string
    {
        return match ($this->refund_method) {
            'store_credit' => __('Store credit'),
            'account' => __('Customer account'),
            default => PaymentMethod::tryFrom($this->refund_method)?->label() ?? $this->refund_method,
        };
    }
}
