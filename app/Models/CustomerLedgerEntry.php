<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerLedgerEntry extends Model
{
    protected $fillable = ['customer_id', 'branch_id', 'account', 'type', 'debit', 'credit', 'balance_after', 'reference_type', 'reference_id', 'due_date', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'balance_after' => 'decimal:2', 'due_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
