<?php

namespace Database\Seeders;

use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Reseller;
use Illuminate\Database\Seeder;

class PriceRuleSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->pluck('id', 'code');
        $plaza = Reseller::query()->where('name', 'Plaza')->value('id');

        $rules = [
            // Default prices (everyone)
            ['CR', null, '2026-09-12', 740, 'Starting price'],
            ['CB', null, '2026-09-12', 810, 'Starting price'],
            ['PR', null, '2026-09-12', 350, 'Starting price'],
            ['CC', null, '2026-09-12', 800, 'Starting price'],
            ['CR', null, '2026-09-27', 730, 'Price change 740 → 730'],
            ['CB', null, '2026-09-27', 800, 'Price change 810 → 800'],
            // Plaza special prices
            ['CR', $plaza, '2026-09-12', 800, 'Plaza special price'],
            ['CB', $plaza, '2026-09-12', 850, 'Plaza special price'],
            ['PR', $plaza, '2026-09-12', 400, 'Plaza special price'],
            ['CC', $plaza, '2026-09-12', 800, 'Plaza special price'],
        ];

        foreach ($rules as [$code, $resellerId, $date, $price, $notes]) {
            PriceRule::query()->firstOrCreate(
                ['product_id' => $products[$code], 'reseller_id' => $resellerId, 'effective_date' => $date],
                ['reseller_price' => $price, 'notes' => $notes],
            );
        }
    }
}
