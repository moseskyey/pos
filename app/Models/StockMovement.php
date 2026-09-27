<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable stock ledger entry.
 */
class StockMovement extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'product_id', 'batch_id', 'type', 'quantity', 'unit_cost', 'balance_after',
        'reference_type', 'reference_id', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'balance_after' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id')->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function referenceNumber(): ?string
    {
        return $this->reference?->number ?? null;
    }
}
