<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTake extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'number', 'scope', 'category_id', 'status', 'note', 'created_by', 'approved_by', 'frozen_at', 'submitted_at', 'posted_at'];

    protected function casts(): array
    {
        return ['frozen_at' => 'datetime', 'submitted_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function isEditable(): bool
    {
        return $this->status === 'counting';
    }
}
