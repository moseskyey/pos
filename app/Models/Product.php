<?php

namespace App\Models;

use App\Enums\TaxType;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'parent_id', 'name', 'sku', 'category_id', 'brand_id', 'unit_id',
        'cost_price', 'retail_price', 'wholesale_price', 'wholesale_min_qty', 'tax_type',
        'reorder_level', 'track_stock', 'track_batches', 'is_weighted', 'has_variants', 'is_bundle', 'variant_attributes',
        'image_path', 'description', 'is_active',
    ];

    protected $attributes = [
        'cost_price' => 0,
        'retail_price' => 0,
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_stock' => true,
        'track_batches' => false,
        'is_weighted' => false,
        'has_variants' => false,
        'is_bundle' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'wholesale_min_qty' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'tax_type' => TaxType::class,
            'track_stock' => 'boolean',
            'track_batches' => 'boolean',
            'is_weighted' => 'boolean',
            'has_variants' => 'boolean',
            'is_bundle' => 'boolean',
            'variant_attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'category_id', 'brand_id', 'cost_price', 'retail_price', 'wholesale_price', 'tax_type', 'reorder_level', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships ----------------------------------------------------------

    /** Components issued from stock when this bundle / kit is sold. */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id');
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(BranchPrice::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class)->where('branch_id', app(BranchContext::class)->currentId() ?? 0);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes -------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Products that can be sold/stocked (variant parents are containers only). */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('has_variants', false);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "{$term}%")
            ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', $term)));
    }

    // Helpers ------------------------------------------------------------------

    public function taxRate(): string
    {
        return ($this->tax_type ?? TaxType::Standard)->rate();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? route('files.show', ['path' => $this->image_path]) : null;
    }

    public function primaryBarcode(): ?string
    {
        return $this->relationLoaded('barcodes')
            ? $this->barcodes->firstWhere('product_unit_id', null)?->barcode ?? $this->barcodes->first()?->barcode
            : $this->barcodes()->whereNull('product_unit_id')->value('barcode');
    }

    public function marginPercent(): ?float
    {
        if (Money::isZero($this->retail_price)) {
            return null;
        }

        return round((float) Money::div(Money::mul(Money::sub($this->retail_price, $this->cost_price), 100), $this->retail_price), 1);
    }

    public function variantLabel(): string
    {
        return collect($this->variant_attributes ?? [])->filter()->join(' / ');
    }

    public function displayName(): string
    {
        return $this->name;
    }

    /** Quantity in stock at a branch (defaults to current branch). */
    public function stockAt(?int $branchId = null): string
    {
        $branchId ??= app(BranchContext::class)->currentId();
        if ($branchId === null) {
            return (string) $this->stocks()->sum('quantity');
        }

        return (string) ($this->stocks()->where('branch_id', $branchId)->value('quantity') ?? '0');
    }
}
