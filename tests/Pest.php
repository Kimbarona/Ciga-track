<?php

use App\Models\Distribution;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Reseller;
use App\Models\User;
use App\Services\DistributionService;
use App\Services\PurchaseService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\PriceRuleSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ResellerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every test runs against the dedicated smoke_app_db_test MySQL database
| (see phpunit.xml / .env.testing). Never point this at smoke_app_db.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function superAdmin(array $attributes = []): User
{
    return User::factory()->superAdmin()->create($attributes);
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

function staff(array $attributes = []): User
{
    return User::factory()->staff()->create($attributes);
}

/**
 * Products CR/CB/PR/CC, resellers Retail/Plaza/Tito Richie/Kariz Store and the
 * spec's price rules (defaults from Sep 12, 2026; CR/CB change on Sep 27; Plaza specials).
 */
function seedCatalog(): void
{
    (new ProductSeeder)->run();
    (new ResellerSeeder)->run();
    (new PriceRuleSeeder)->run();
    (new ExpenseCategorySeeder)->run();
}

function product(string $code): Product
{
    return Product::query()->where('code', $code)->firstOrFail();
}

function reseller(string $name): Reseller
{
    return Reseller::query()->where('name', $name)->firstOrFail();
}

function stockUp(string $code, int $qty, float $unitCost = 680, string $date = '2026-09-11'): Purchase
{
    return app(PurchaseService::class)->create([
        'date' => $date, 'product_id' => product($code)->id, 'qty' => $qty, 'unit_cost' => $unitCost,
    ], null);
}

function distribute(string $reseller, string $code, int $qty, string $date, ?User $actor = null, array $extra = []): Distribution
{
    return app(DistributionService::class)->create([
        'date' => $date, 'reseller_id' => reseller($reseller)->id, 'product_id' => product($code)->id, 'qty' => $qty, ...$extra,
    ], $actor);
}
