<?php

namespace App\Models;

use App\Support\Money;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'product_unit_id', 'name', 'sku', 'unit_name', 'conversion_factor', 'quantity', 'base_quantity',
        'unit_price', 'list_price', 'price_tier', 'cost_price', 'discount_type', 'discount_value', 'discount_amount', 'cart_discount_share',
        'tax_type', 'tax_rate', 'tax_amount', 'line_total', 'returned_quantity',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'list_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'cart_discount_share' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'returned_quantity' => 'decimal:3',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withoutGlobalScopes();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function returnableQuantity(): string
    {
        return Qty::sub($this->quantity, $this->returned_quantity);
    }

    /** Net revenue for the line after the cart discount share (VAT-inclusive when prices include VAT). */
    public function netTotal(): string
    {
        return Money::sub($this->line_total, $this->cart_discount_share);
    }

    public function totalCost(): string
    {
        return Money::mul($this->cost_price, $this->quantity);
    }
}
