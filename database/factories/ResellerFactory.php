<?php

namespace Database\Factories;

use App\Enums\ResellerType;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reseller>
 */
class ResellerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact' => fake()->numerify('09#########'),
            'location' => fake()->city(),
            'type' => ResellerType::Reseller,
            'is_active' => true,
        ];
    }

    public function retail(): static
    {
        return $this->state(fn () => ['name' => 'Retail', 'type' => ResellerType::Retail]);
    }
}
