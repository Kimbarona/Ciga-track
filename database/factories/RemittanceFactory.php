<?php

namespace Database\Factories;

use App\Enums\RemittanceMode;
use App\Enums\RemittanceStatus;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Remittance>
 */
class RemittanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => '2026-09-13',
            'reseller_id' => Reseller::factory(),
            'amount' => 1000,
            'mode' => RemittanceMode::Cash,
            'status' => RemittanceStatus::Confirmed,
            'confirmed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => RemittanceStatus::Pending, 'confirmed_at' => null]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => RemittanceStatus::Rejected, 'confirmed_at' => null, 'rejection_reason' => 'Wrong amount']);
    }
}
