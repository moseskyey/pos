<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'customer_id', 'shift_id', 'user_id', 'number', 'amount', 'method', 'reference', 'note', 'idempotency_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'method' => PaymentMethod::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }
}
