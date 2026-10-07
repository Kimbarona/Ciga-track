<?php

namespace Database\Factories;

use App\Enums\PriceSource;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw rows only; real distributions go through DistributionService.
 *
 * @extends Factory<Distribution>
 */
class DistributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => '2026-09-12',
            'reseller_id' => Reseller::factory(),
            'product_id' => Product::factory(),
            'qty' => 1,
            'unit_price' => 740,
            'price_source' => PriceSource::Default,
        ];
    }

    public function voided(): static
    {
        return $this->state(fn () => ['voided_at' => now(), 'void_reason' => 'Test void']);
    }
}
