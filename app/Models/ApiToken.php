<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal API token. The plain token is shown once: "dk_{business id}_{secret}";
 * only a SHA-256 hash of the secret is stored.
 */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token_hash', 'prefix', 'abilities', 'last_used_at', 'last_used_ip', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
