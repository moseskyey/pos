<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use BelongsToBranch;

    public const STATUSES = ['draft', 'sent', 'partially_received', 'received', 'cancelled'];

    protected $fillable = ['branch_id', 'supplier_id', 'number', 'status', 'order_date', 'expected_date', 'subtotal', 'tax_total', 'total', 'note', 'created_by', 'sent_at'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'sent_at' => 'datetime', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['draft', 'sent', 'partially_received'], true);
    }
}
