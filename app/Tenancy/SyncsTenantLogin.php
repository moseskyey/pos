<?php

namespace App\Tenancy;

use App\Models\Platform\TenantLogin;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the central sign-in index (email/phone → business) in step with a
 * business's users, and keeps emails and phones unique across all businesses.
 */
trait SyncsTenantLogin
{
    protected static function bootSyncsTenantLogin(): void
    {
        static::saving(function (self $user) {
            if (! tenant() || ! ($user->isDirty('email') || $user->isDirty('phone'))) {
                return;
            }
            foreach (['email' => $user->email ? strtolower($user->email) : null, 'phone' => $user->phone] as $field => $value) {
                if ($value && $user->isDirty($field) && static::loginTakenElsewhere($field, $value, $user)) {
                    throw ValidationException::withMessages([
                        $field => $field === 'email'
                            ? __('This email is already used by another account.')
                            : __('This phone number is already used by another account.'),
                    ]);
                }
            }
        });

        static::saved(fn (self $user) => $user->syncTenantLogin());
        static::deleted(fn (self $user) => $user->syncTenantLogin());
        static::restored(fn (self $user) => $user->syncTenantLogin());
        static::forceDeleted(function (self $user) {
            if ($tenant = tenant()) {
                TenantLogin::where('tenant_id', $tenant->id)->where('user_id', $user->getKey())->delete();
            }
        });
    }

    protected static function loginTakenElsewhere(string $field, string $value, self $user): bool
    {
        return TenantLogin::where($field, $value)
            ->where(fn ($q) => $q->where('tenant_id', '!=', tenant()->id)->orWhere('user_id', '!=', $user->getKey() ?? 0))
            ->exists();
    }

    public function syncTenantLogin(): void
    {
        if (! $tenant = tenant()) {
            return;
        }

        TenantLogin::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $this->getKey()],
            [
                'name' => $this->name,
                'email' => $this->email ? strtolower($this->email) : null,
                'phone' => $this->phone ?: null,
                'is_active' => (bool) $this->is_active && ! $this->trashed(),
            ],
        );
    }
}
