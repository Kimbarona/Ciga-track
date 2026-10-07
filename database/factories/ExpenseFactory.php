<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => '2026-09-15',
            'expense_category_id' => fn () => ExpenseCategory::query()->firstOrCreate(['name' => 'Other'])->id,
            'description' => 'Gas',
            'amount' => 100,
            'paid_via' => 'Cash',
        ];
    }

    public function voided(): static
    {
        return $this->state(fn () => ['voided_at' => now(), 'void_reason' => 'Test void']);
    }
}
