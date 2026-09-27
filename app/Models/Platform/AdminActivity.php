<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActivity extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $fillable = ['admin_id', 'tenant_id', 'action', 'description', 'properties', 'ip'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** Record what a platform admin did. */
    public static function record(string $action, string $description, ?Tenant $tenant = null, array $properties = []): self
    {
        return static::create([
            'admin_id' => auth('admin')->id(),
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
