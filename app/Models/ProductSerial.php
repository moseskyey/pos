<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One serial / IMEI-numbered unit of a product: in stock at a branch, sold
 * (with its customer and warranty end date) or defective.
 */
class ProductSerial extends Model
{
    use BelongsToBranch;

    protected $fillable = ['product_id', 'branch_id', 'serial', 'status', 'goods_receipt_id', 'sale_id', 'sale_item_id', 'customer_id', 'sold_at', 'warranty_until', 'note'];

    protected $attributes = ['status' => 'in_stock'];

    protected function casts(): array
    {
        return ['sold_at' => 'datetime', 'warranty_until' => 'date'];
    }

    /** Serials are stored upper-case without spaces, as scanners and customers type them differently. */
    public static function normalize(?string $serial): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $serial));
    }

    public function underWarranty(): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->gte(today());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
