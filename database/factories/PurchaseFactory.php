<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => '2026-09-11',
            'product_id' => Product::factory(),
            'qty' => 10,
            'unit_cost' => 680,
            'supplier' => 'Tatay',
        ];
    }

    public function voided(): static
    {
        return $this->state(fn () => ['voided_at' => now(), 'void_reason' => 'Test void']);
    }
}
