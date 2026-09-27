<?php

namespace App\Models\Platform;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    public const LIMITS = ['branches' => 'max_branches', 'users' => 'max_users', 'products' => 'max_products'];

    protected $connection = 'central';

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'interval_months', 'max_branches', 'max_users', 'max_products',
        'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'interval_months' => 'integer',
            'max_branches' => 'integer',
            'max_users' => 'integer',
            'max_products' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function limit(string $resource): ?int
    {
        $column = self::LIMITS[$resource] ?? null;

        return $column ? $this->{$column} : null;
    }

    /** Monthly equivalent, for MRR. */
    public function monthlyPrice(): string
    {
        return Money::div($this->price, max(1, $this->interval_months), 2);
    }

    public function intervalLabel(): string
    {
        return match ($this->interval_months) {
            1 => __('month'),
            3 => __('3 months'),
            6 => __('6 months'),
            12 => __('year'),
            default => trans_choice(':count month|:count months', $this->interval_months),
        };
    }
}
