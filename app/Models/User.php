<?php

namespace App\Models;

use App\Support\PhoneNumber;
use App\Tenancy\SyncsTenantLogin;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes, SyncsTenantLogin;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'is_active', 'avatar_path',
        'locale', 'theme', 'default_branch_id', 'commission_rate',
    ];

    protected $hidden = ['password', 'remember_token', 'pin', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'pin_locked_until' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'is_active', 'default_branch_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => PhoneNumber::normalize($value) ?? ($value ?: null));
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function setPin(?string $pin): void
    {
        $this->forceFill(['pin' => $pin ? Hash::make($pin) : null, 'pin_attempts' => 0, 'pin_locked_until' => null])->save();
    }

    public function hasPin(): bool
    {
        return ! empty($this->pin);
    }

    public function pinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('files.show', ['path' => $this->avatar_path]) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    public function roleLabel(): string
    {
        $role = $this->roles->first()?->name;

        return $role ? __(config("dukapos.roles.$role.label") ?? ucfirst($role)) : __('No role');
    }

    public function deleteAvatar(): void
    {
        if ($this->avatar_path) {
            Storage::disk('local')->delete($this->avatar_path);
        }
    }
}
