<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = fake()->numberBetween(2, 200) * 100;

        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####??')),
            'unit_id' => Unit::firstOrCreate(['short_name' => 'pc'], ['name' => 'Piece'])->id,
            'cost_price' => $cost,
            'retail_price' => $cost + fake()->numberBetween(1, 50) * 50,
            'tax_type' => 'standard',
            'reorder_level' => 5,
            'track_stock' => true,
            'is_active' => true,
        ];
    }

    public function batched(): static
    {
        return $this->state(fn () => ['track_batches' => true]);
    }

    public function service(): static
    {
        return $this->state(fn () => ['track_stock' => false]);
    }
}
