<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Sale extends Model
{
    use BelongsToBranch;

    /** Reasons an offline sale is flagged for a manager (see SaleService::recordOffline). */
    public const REVIEW_FLAGS = [
        'price_override' => 'Price changed since the till went offline',
        'discount' => 'Discount without approval',
        'below_cost' => 'Sold below cost',
        'negative_stock' => 'Sold more than the recorded stock',
        'short_paid' => 'Paid less than the total',
        'missing_reference' => 'Mobile money reference missing',
        'shift_closed' => 'Synced after the shift was closed',
        'inactive_product' => 'Product was deactivated',
        'credit_limit' => 'Credit limit exceeded',
        'gift_card' => 'Gift card could not be checked',
        'prescription' => 'Prescription number missing',
    ];

    protected $fillable = [
        'branch_id', 'register_id', 'shift_id', 'user_id', 'salesperson_id', 'customer_id', 'number', 'status',
        'subtotal', 'discount_total', 'tax_total', 'rounding', 'total', 'paid_total', 'tendered', 'change_due', 'balance_due', 'due_date',
        'cart_discount_type', 'cart_discount_value', 'hold_note', 'note', 'prescription_ref', 'prescriber', 'idempotency_key', 'valid_until', 'converted_sale_id', 'synced_at', 'review_flags',
        'completed_at', 'voided_at', 'voided_by', 'void_reason', 'reprint_count', 'fiscal_code', 'fiscal_qr', 'loyalty_earned', 'loyalty_redeemed',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'rounding' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'cart_discount_value' => 'decimal:2',
            'valid_until' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
            'review_flags' => 'array',
            'voided_at' => 'datetime',
        ];
    }

    /** Who made the sale for commission (defaults to the cashier). */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class)->withoutGlobalScopes();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withoutGlobalScopes();
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_sale_id')->withoutGlobalScopes();
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Completed);
    }

    /** Sales that count as revenue (completed + layaway once fully paid are completed). */
    public function scopeRevenue(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('status'), SaleStatus::Completed->value);
    }

    public function isVoidable(): bool
    {
        return $this->status === SaleStatus::Completed && $this->created_at->isToday() && (! class_exists(SaleReturn::class) || ! $this->returns()->exists());
    }

    public function verificationUrl(): string
    {
        return URL::signedRoute('receipts.verify', ['number' => $this->number ?? $this->id]);
    }

    public function itemCount(): string
    {
        return (string) $this->items->sum('quantity');
    }

    /** Unpaid past its due date. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null && (float) $this->balance_due > 0 && $this->due_date->lt(today());
    }
}
