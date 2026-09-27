<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'supplier_id', 'goods_receipt_id', 'bill_no', 'bill_date', 'due_date', 'subtotal', 'tax_total', 'total', 'paid', 'status', 'description', 'user_id'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class)->withoutGlobalScopes();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function balance(): string
    {
        return Money::sub($this->total, $this->paid);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function refreshStatus(): void
    {
        $this->status = Money::lte($this->total, $this->paid) ? 'paid' : (Money::isPositive($this->paid) ? 'partial' : 'unpaid');
    }
}
