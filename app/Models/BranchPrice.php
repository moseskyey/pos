<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Selling price override for a product (or one of its units) at one branch.
 * Catalog configuration, always looked up with an explicit branch id, so it
 * does not use the BelongsToBranch scope.
 */
class BranchPrice extends Model
{
    protected $fillable = ['branch_id', 'product_id', 'product_unit_id', 'retail_price', 'wholesale_price'];

    protected function casts(): array
    {
        return ['retail_price' => 'decimal:2', 'wholesale_price' => 'decimal:2'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
