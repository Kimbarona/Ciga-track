<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('??'));

        return [
            'code' => $code,
            'name' => $code.' ream',
            'reorder_level' => 2,
            'is_active' => true,
        ];
    }
}
