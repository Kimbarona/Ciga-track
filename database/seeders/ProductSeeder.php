<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => 'CR', 'name' => 'CR ream', 'reorder_level' => 5],
            ['code' => 'CB', 'name' => 'CB ream', 'reorder_level' => 2],
            ['code' => 'PR', 'name' => 'PR ream', 'reorder_level' => 2],
            ['code' => 'CC', 'name' => 'CC ream', 'reorder_level' => 1],
        ];

        foreach ($products as $product) {
            Product::withTrashed()->firstOrCreate(['code' => $product['code']], $product);
        }
    }
}
