<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Supplier extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'tin', 'vrn', 'address', 'payment_terms_days', 'opening_balance', 'notes', 'is_active'];

    protected $attributes = ['payment_terms_days' => 30, 'balance' => 0, 'opening_balance' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'balance' => 'decimal:2', 'is_active' => 'boolean', 'payment_terms_days' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'phone', 'email', 'tin', 'payment_terms_days', 'is_active'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => PhoneNumber::normalize($value) ?? ($value ?: null));
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class)->orderBy('id');
    }

    public function displayPhone(): string
    {
        return $this->phone ? PhoneNumber::display($this->phone) : '';
    }
}
