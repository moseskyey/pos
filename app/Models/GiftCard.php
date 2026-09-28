<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Gift card (paid for) or voucher (complimentary) with a spendable balance.
 * The balance can be spent at any branch, so cards are not branch-scoped;
 * branch_id records where the card was issued.
 */
class GiftCard extends Model
{
    protected $fillable = ['code', 'kind', 'initial_value', 'balance', 'customer_id', 'branch_id', 'expires_on', 'is_active', 'note', 'issued_by'];

    protected $attributes = ['kind' => 'gift_card', 'is_active' => true];

    protected function casts(): array
    {
        return ['initial_value' => 'decimal:2', 'balance' => 'decimal:2', 'expires_on' => 'date', 'is_active' => 'boolean'];
    }

    /** Codes are stored upper-case without separators; people type them in any form. */
    public static function normalizeCode(?string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }

    /** XXXX-XXXX-XXXX for printing and display. */
    public function displayCode(): string
    {
        return implode('-', str_split($this->code, 4));
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired() && (float) $this->balance > 0;
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            $this->isExpired() => 'expired',
            (float) $this->balance <= 0 => 'used',
            default => 'active',
        };
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->latest('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
