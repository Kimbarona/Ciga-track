<?php

namespace Database\Factories;

use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceRule>
 */
class PriceRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'reseller_id' => null,
            'effective_date' => '2026-09-12',
            'reseller_price' => 740,
            'retail_price' => 800,
        ];
    }
}
