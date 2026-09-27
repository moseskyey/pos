<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'product_id', 'batch_no', 'expiry_date', 'quantity', 'cost_price'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'quantity' => 'decimal:3', 'cost_price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function daysToExpiry(): ?int
    {
        return $this->expiry_date ? (int) now()->startOfDay()->diffInDays($this->expiry_date, false) : null;
    }

    public function expiryStatus(): string
    {
        $days = $this->daysToExpiry();

        return match (true) {
            $days === null => 'no_expiry',
            $days < 0 => 'expired',
            $days <= (int) setting('inventory.expiry_alert_days', 30) => 'expiring',
            default => 'active',
        };
    }
}
