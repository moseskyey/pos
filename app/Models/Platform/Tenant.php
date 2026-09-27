<?php

namespace App\Models\Platform;

use App\Support\PlatformSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A business (shop) using DukaPOS. Its data lives in its own database; the
 * subscription decides whether its users can sign in and work.
 *
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $paid_until
 * @property Carbon|null $suspended_at
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    public const TRIAL = 'trial';

    public const ACTIVE = 'active';

    public const GRACE = 'grace';

    public const EXPIRED = 'expired';

    public const SUSPENDED = 'suspended';

    protected $connection = 'central';

    protected $fillable = [
        'name', 'slug', 'database', 'storage_folder', 'owner_name', 'owner_email', 'owner_phone', 'plan_id',
        'trial_ends_at', 'paid_until', 'suspended_at', 'suspension_reason', 'provisioned_at', 'last_activity_at',
        'last_reminder_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'paid_until' => 'datetime',
            'suspended_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_reminder_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function logins(): HasMany
    {
        return $this->hasMany(TenantLogin::class);
    }

    /** Database connection name used while this business is active. */
    public function connectionName(): string
    {
        return 'tenant_'.$this->getKey();
    }

    /** When access ends: the later of the trial and the paid period. */
    public function accessEndsAt(): ?Carbon
    {
        $dates = array_filter([$this->trial_ends_at, $this->paid_until]);

        return $dates ? max($dates) : null;
    }

    public function graceEndsAt(): ?Carbon
    {
        return $this->accessEndsAt()?->copy()->addDays((int) PlatformSettings::get('grace_days', 3));
    }

    public function status(): string
    {
        if ($this->suspended_at) {
            return self::SUSPENDED;
        }
        $now = now();
        if ($this->paid_until && $this->paid_until->gte($now)) {
            return self::ACTIVE;
        }
        if ($this->trial_ends_at && $this->trial_ends_at->gte($now)) {
            return self::TRIAL;
        }
        $grace = $this->graceEndsAt();

        return $grace && $grace->gte($now) ? self::GRACE : self::EXPIRED;
    }

    public function canUseApp(): bool
    {
        return in_array($this->status(), [self::TRIAL, self::ACTIVE, self::GRACE], true);
    }

    /** Calendar days until access ends: 0 = ends today, 1 = tomorrow. */
    public function daysLeft(): int
    {
        $end = $this->accessEndsAt();

        return $end ? max(0, (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false)) : 0;
    }

    public static function statusLabels(): array
    {
        return [
            self::TRIAL => __('Trial'),
            self::ACTIVE => __('Active'),
            self::GRACE => __('Grace period'),
            self::EXPIRED => __('Expired'),
            self::SUSPENDED => __('Suspended'),
        ];
    }

    public static function statusColor(string $status): string
    {
        return [
            self::TRIAL => 'info',
            self::ACTIVE => 'success',
            self::GRACE => 'warning',
            self::EXPIRED => 'danger',
            self::SUSPENDED => 'secondary',
        ][$status] ?? 'secondary';
    }

    /** Filter by computed status in SQL (mirrors status()). */
    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        $now = now();
        $graceStart = $now->copy()->subDays((int) PlatformSettings::get('grace_days', 3));
        $notActive = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '<', $now));
        $notTrial = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', $now));
        // Later of the two end dates, for the grace window.
        $ends = fn (Builder $q, string $op, Carbon $at) => $q->where(fn ($w) => $w->where('paid_until', $op, $at)->orWhere('trial_ends_at', $op, $at));

        return match ($status) {
            self::SUSPENDED => $query->whereNotNull('suspended_at'),
            self::ACTIVE => $query->whereNull('suspended_at')->where('paid_until', '>=', $now),
            self::TRIAL => $query->whereNull('suspended_at')->tap($notActive)->where('trial_ends_at', '>=', $now),
            self::GRACE => $query->whereNull('suspended_at')->tap($notActive)->tap($notTrial)->tap(fn ($q) => $ends($q, '>=', $graceStart)),
            self::EXPIRED => $query->whereNull('suspended_at')->tap($notActive)->tap($notTrial)
                ->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '<', $graceStart))
                ->where(fn ($w) => $w->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', $graceStart)),
            default => $query,
        };
    }
}
