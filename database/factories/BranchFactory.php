<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Kariakoo', 'Mbezi', 'Sinza', 'Kinondoni', 'Mwenge', 'Tegeta', 'Arusha CBD', 'Mwanza Rock City', 'Dodoma Centre', 'Moshi Town']).' '.fake()->unique()->numberBetween(1, 999),
            'code' => strtoupper(fake()->unique()->bothify('BR##??')),
            'address' => fake()->streetAddress(),
            'phone' => '2557'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
