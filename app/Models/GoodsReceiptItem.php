<?php

namespace App\Models;

use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = ['goods_receipt_id', 'purchase_order_item_id', 'product_id', 'quantity', 'returned_quantity', 'unit_cost', 'batch_no', 'expiry_date', 'tax_rate', 'tax_amount', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'returned_quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'expiry_date' => 'date', 'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id')->withoutGlobalScopes();
    }

    public function returnable(): string
    {
        return Qty::sub($this->quantity, $this->returned_quantity);
    }
}
