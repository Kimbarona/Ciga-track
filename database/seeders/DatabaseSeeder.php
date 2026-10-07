<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Every seeder is idempotent.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ExpenseCategorySeeder::class,
            ProductSeeder::class,
            ResellerSeeder::class,
            PriceRuleSeeder::class,
        ]);
    }
}
