<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

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
        self::FAILED => [],
    ];

    protected $fillable = ['branch_id', 'user_id', 'sale_id', 'reference', 'gateway', 'method', 'phone', 'amount', 'status', 'provider_reference', 'message', 'payload', 'status_checks', 'completed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payload' => 'array', 'completed_at' => 'datetime'];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED], true);
    }
}
