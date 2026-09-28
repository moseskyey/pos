<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Automatic discount rule applied at the till.
 *
 * Types: percent (% off), amount (TSh off each unit), buy_get (buy X get Y
 * free), multi_price (N items for TSh P). Targets every product, some
 * categories or some products, optionally limited to branches, dates,
 * weekdays and a time window (happy hour).
 */
class Promotion extends Model
{
    use LogsActivity;

    public const TYPES = ['percent', 'amount', 'buy_get', 'multi_price'];

    protected $fillable = [
        'name', 'type', 'value', 'buy_qty', 'get_qty', 'min_qty', 'applies_to', 'branch_ids', 'days_of_week',
        'start_time', 'end_time', 'starts_on', 'ends_on', 'priority', 'is_active', 'created_by',
    ];

    protected $attributes = ['applies_to' => 'all', 'priority' => 0, 'is_active' => true, 'value' => 0];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'buy_qty' => 'decimal:3',
            'get_qty' => 'decimal:3',
            'min_qty' => 'decimal:3',
            'branch_ids' => 'array',
            'days_of_week' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('promotions');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Active and inside its date range on the given day (time/weekday checked by runsAt()). */
    public function scopeCurrent(Builder $query, ?Carbon $at = null): Builder
    {
        $day = ($at ?? now())->toDateString();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day));
    }

    public function runsAt(Carbon $at, ?int $branchId): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_on && $at->lt($this->starts_on->copy()->startOfDay())) {
            return false;
        }
        if ($this->ends_on && $at->gt($this->ends_on->copy()->endOfDay())) {
            return false;
        }
        if ($this->branch_ids && $branchId && ! in_array($branchId, array_map('intval', $this->branch_ids), true)) {
            return false;
        }
        if ($this->days_of_week && ! in_array($at->dayOfWeekIso, array_map('intval', $this->days_of_week), true)) {
            return false;
        }
        if ($this->start_time && $this->end_time) {
            $time = $at->format('H:i:s');
            $from = Carbon::parse($this->start_time)->format('H:i:s');
            $to = Carbon::parse($this->end_time)->format('H:i:s');
            // A window like 22:00–02:00 runs past midnight.
            $inside = $from <= $to ? ($time >= $from && $time <= $to) : ($time >= $from || $time <= $to);
            if (! $inside) {
                return false;
            }
        }

        return true;
    }

    public function appliesToProduct(Product $product): bool
    {
        return match ($this->applies_to) {
            'products' => $this->targets->contains(fn ($t) => $t->product_id === $product->id || ($product->parent_id && $t->product_id === $product->parent_id)),
            'categories' => $product->category_id !== null && $this->targets->contains(fn ($t) => $t->category_id !== null
                && ($t->category_id === $product->category_id || $t->category_id === $product->category?->parent_id)),
            default => true,
        };
    }

    /** Short customer-facing description, e.g. "Buy 2 get 1 free". */
    public function summary(): string
    {
        $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');

        return match ($this->type) {
            'percent' => __(':v% off', ['v' => $qty($this->value)]),
            'amount' => __(':v off each', ['v' => money($this->value)]),
            'buy_get' => __('Buy :x get :y free', ['x' => $qty($this->buy_qty), 'y' => $qty($this->get_qty)]),
            'multi_price' => __(':n for :p', ['n' => $qty($this->buy_qty), 'p' => money($this->value)]),
            default => $this->name,
        };
    }
}
