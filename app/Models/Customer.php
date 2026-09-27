<?php

namespace App\Models;

use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'tin', 'address', 'type', 'credit_limit', 'opening_balance', 'notes', 'is_active'];

    protected $attributes = ['type' => 'retail', 'credit_limit' => 0, 'balance' => 0, 'store_credit' => 0, 'loyalty_points' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'store_credit' => 'decimal:2',
            'loyalty_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'phone', 'email', 'type', 'credit_limit', 'is_active'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => PhoneNumber::normalize($value) ?? ($value ?: null));
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest('id');
    }

    public function isWholesale(): bool
    {
        return $this->type === 'wholesale';
    }

    public function availableCredit(): string
    {
        return Money::sub($this->credit_limit, $this->balance);
    }

    public function displayPhone(): string
    {
        return $this->phone ? PhoneNumber::display($this->phone) : '';
    }
}
