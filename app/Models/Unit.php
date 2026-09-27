<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'short_name', 'allow_decimal', 'is_active'];

    protected function casts(): array
    {
        return ['allow_decimal' => 'boolean', 'is_active' => 'boolean'];
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function label(): string
    {
        return $this->name.' ('.$this->short_name.')';
    }
}
