<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Category extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['parent_id', 'name', 'description', 'color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /** Self + child ids, for filtering products by a parent category. */
    public function descendantIds(): array
    {
        return [$this->id, ...self::query()->where('parent_id', $this->id)->pluck('id')->all()];
    }

    /** Options list with nested labels for selects. */
    public static function options(bool $onlyActive = true): array
    {
        $all = self::query()->when($onlyActive, fn ($q) => $q->where('is_active', true))->orderBy('sort_order')->orderBy('name')->get();
        $out = [];
        foreach ($all->whereNull('parent_id') as $parent) {
            $out[$parent->id] = $parent->name;
            foreach ($all->where('parent_id', $parent->id) as $child) {
                $out[$child->id] = '— '.$child->name;
            }
        }

        return $out;
    }
}
