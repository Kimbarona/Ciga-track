<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
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
