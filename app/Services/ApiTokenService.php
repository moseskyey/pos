<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * API tokens look like "dk_{business id}_{40 random characters}". The business
 * id picks the database; only a SHA-256 hash of the whole token is stored.
 */
class ApiTokenService
{
    public const PATTERN = '/^dk_(\d+)_([A-Za-z0-9]{40})$/';

    /** @return array{0: ApiToken, 1: string} the token and its plain text (shown once) */
    public function create(User $user, string $name, bool $canWrite = false, ?int $expiresInDays = null): array
    {
        $plain = 'dk_'.tenant()->getKey().'_'.Str::random(40);
        $token = ApiToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'prefix' => substr($plain, 0, strlen('dk_'.tenant()->getKey().'_') + 4),
            'abilities' => $canWrite ? ['read', 'write'] : ['read'],
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
        ]);
        activity('auth')->causedBy($user)->performedOn($token)->withProperties(['name' => $name, 'abilities' => $token->abilities])->log('API token created');

        return [$token, $plain];
    }

    public function find(string $plain): ?ApiToken
    {
        return ApiToken::with('user')->where('token_hash', hash('sha256', $plain))->first();
    }

    public function revoke(ApiToken $token, User $by): void
    {
        activity('auth')->causedBy($by)->performedOn($token)->withProperties(['name' => $token->name])->log('API token revoked');
        $token->delete();
    }

    /** The business id inside a token, before any database lookup. */
    public static function tenantIdFrom(?string $plain): ?int
    {
        return $plain && preg_match(self::PATTERN, $plain, $m) ? (int) $m[1] : null;
    }
}
