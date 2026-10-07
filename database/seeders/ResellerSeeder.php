<?php

namespace Database\Seeders;

use App\Enums\ResellerType;
use App\Models\Reseller;
use Illuminate\Database\Seeder;

class ResellerSeeder extends Seeder
{
    public function run(): void
    {
        $resellers = [
            ['name' => 'Retail', 'type' => ResellerType::Retail, 'notes' => 'Walk-in customers (uses retail prices).'],
            ['name' => 'Plaza', 'type' => ResellerType::Reseller, 'location' => 'San Juan'],
            ['name' => 'Tito Richie', 'type' => ResellerType::Reseller, 'location' => 'San Juan'],
            ['name' => 'Kariz Store', 'type' => ResellerType::Reseller, 'location' => 'Cansinala'],
        ];

        foreach ($resellers as $reseller) {
            Reseller::withTrashed()->firstOrCreate(['name' => $reseller['name']], $reseller);
        }
    }
}
