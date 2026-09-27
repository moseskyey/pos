<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function openShift(): ?Shift
    {
        return $this->shifts()->where('status', 'open')->latest('opened_at')->first();
    }
}
