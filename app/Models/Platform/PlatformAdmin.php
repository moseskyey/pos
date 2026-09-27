<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A DukaPOS platform administrator (the company running the service), not a shop user.
 */
class PlatformAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'central';

    protected $fillable = ['name', 'email', 'password', 'is_super', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
