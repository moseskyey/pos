<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupplierLedgerEntry extends Model
{
    protected $fillable = ['supplier_id', 'branch_id', 'type', 'debit', 'credit', 'balance_after', 'reference_type', 'reference_id', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
