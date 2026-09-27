<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Secondary selling unit for a product: 1 [unit] = factor × base unit, with its own price.
 */
class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'factor', 'retail_price', 'wholesale_price'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4', 'retail_price' => 'decimal:2', 'wholesale_price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }
}
