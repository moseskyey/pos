<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    public const METHODS = ['fastlipa', 'mobile', 'cash', 'bank', 'complimentary'];

    public const TERMINAL = ['completed', 'refunded', 'cancelled'];

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id', 'plan_id', 'number', 'reference', 'amount', 'months', 'method', 'status', 'phone',
        'provider_reference', 'message', 'period_start', 'period_end', 'paid_at', 'applied_at', 'recheck_until',
        'recorded_by', 'initiated_by_user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
            'applied_at' => 'datetime',
            'recheck_until' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'recorded_by');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true)
            || ($this->status === 'failed' && ! $this->recheckable());
    }

    /** A "failed" push can still turn out completed (FastLipa reports late). */
    public function recheckable(): bool
    {
        return $this->status === 'failed' && $this->recheck_until && $this->recheck_until->isFuture();
    }

    public static function methodLabels(): array
    {
        return [
            'fastlipa' => __('Mobile money (FastLipa)'),
            'mobile' => __('Mobile money (manual)'),
            'cash' => __('Cash'),
            'bank' => __('Bank transfer'),
            'complimentary' => __('Complimentary'),
        ];
    }

    public static function statusColor(string $status): string
    {
        return ['completed' => 'success', 'pending' => 'warning', 'processing' => 'info', 'failed' => 'danger', 'refunded' => 'secondary', 'cancelled' => 'secondary'][$status] ?? 'secondary';
    }
}
