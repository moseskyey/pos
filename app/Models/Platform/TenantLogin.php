<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central index of every shop user's email and phone, so sign-in can find the
 * right business. Kept in sync from the User model.
 */
class TenantLogin extends Model
{
    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'user_id', 'name', 'email', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
