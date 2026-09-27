<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoodsReceipt extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'supplier_id', 'purchase_order_id', 'number', 'supplier_invoice_no', 'received_at', 'subtotal', 'tax_total', 'total', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function bill(): HasOne
    {
        return $this->hasOne(SupplierBill::class)->withoutGlobalScopes();
    }
}
