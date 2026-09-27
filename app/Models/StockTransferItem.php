<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'product_id', 'quantity_requested', 'quantity_dispatched', 'quantity_received', 'unit_cost', 'note'];

    protected function casts(): array
    {
        return ['quantity_requested' => 'decimal:3', 'quantity_dispatched' => 'decimal:3', 'quantity_received' => 'decimal:3', 'unit_cost' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
