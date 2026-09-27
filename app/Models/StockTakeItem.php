<?php

namespace App\Models;

use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    protected $fillable = ['stock_take_id', 'product_id', 'expected_quantity', 'counted_quantity', 'unit_cost', 'counted_by', 'counted_at'];

    protected function casts(): array
    {
        return ['expected_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'counted_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variance(): ?string
    {
        return $this->counted_quantity === null ? null : Qty::sub($this->counted_quantity, $this->expected_quantity);
    }
}
