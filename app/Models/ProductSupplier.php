<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who supplies a product: their code for it, the last cost and date received, lead time and preference. */
class ProductSupplier extends Model
{
    protected $fillable = ['product_id', 'supplier_id', 'supplier_sku', 'last_cost', 'last_received_at', 'lead_time_days', 'is_preferred'];

    protected function casts(): array
    {
        return ['last_cost' => 'decimal:2', 'last_received_at' => 'datetime', 'lead_time_days' => 'integer', 'is_preferred' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
