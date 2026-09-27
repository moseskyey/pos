<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A notice from the platform shown at the top of every shop's pages. */
class Announcement extends Model
{
    public const LEVELS = ['info', 'success', 'warning', 'danger'];

    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'title', 'body', 'level', 'starts_at', 'ends_at', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeVisibleTo(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
