<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIntent extends Model
{
    use BelongsToBranch;

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected const TRANSITIONS = [
        self::PENDING => [self::PROCESSING, self::COMPLETED, self::FAILED],
        self::PROCESSING => [self::COMPLETED, self::FAILED],
        self::COMPLETED => [],
        // Only while recheckable(): the provider confirmed the money arrived after reporting a failure.
        self::FAILED => [self::COMPLETED],
    ];

    protected $fillable = ['branch_id', 'user_id', 'sale_id', 'reference', 'gateway', 'method', 'phone', 'amount', 'status', 'provider_reference', 'message', 'payload', 'status_checks', 'completed_at', 'recheck_until', 'late_completed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payload' => 'array', 'completed_at' => 'datetime', 'recheck_until' => 'datetime', 'late_completed_at' => 'datetime'];
    }

    public function canTransitionTo(string $status): bool
    {
        if ($this->status === self::FAILED && ! $this->recheckable()) {
            return false;
        }

        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** A provider-reported failure that may still flip to completed. */
    public function recheckable(): bool
    {
        return $this->status === self::FAILED && $this->provider_reference && $this->recheck_until?->isFuture();
    }

    /** No further status change can happen. */
    public function isTerminal(): bool
    {
        return $this->status === self::COMPLETED || ($this->status === self::FAILED && ! $this->recheckable());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
