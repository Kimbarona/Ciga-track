<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (ExpenseCategory::DEFAULTS as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
