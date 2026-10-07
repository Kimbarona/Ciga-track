<?php

use App\Filament\Pages\AppSettings;
use App\Filament\Resources\Resellers\Pages\ViewReseller;
use App\Filament\Resources\Resellers\RelationManagers\DistributionsRelationManager;
use App\Filament\Resources\Resellers\RelationManagers\RemittancesRelationManager;
use App\Filament\Resources\Resellers\RelationManagers\SpecialPricesRelationManager;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\PendingRemittances;
use App\Filament\Widgets\SalesByProductChart;
use App\Filament\Widgets\SalesVsCollectionsChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopResellersByBalance;
use App\Models\Setting;
use App\Services\RemittanceService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    seedCatalog();
    stockUp('CR', 10, 680);
    stockUp('CB', 3, 750);
    distribute('Tito Richie', 'CR', 6, now()->toDateString());
    app(RemittanceService::class)->record(['date' => now()->toDateString(), 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 1000, 'mode' => 'cash'], staff());
    Filament::setCurrentPanel('admin');
    $this->actingAs(admin());
});

test('stats overview shows period figures', function () {
    Livewire::test(StatsOverview::class, ['pageFilters' => ['preset' => 'all']])
        ->assertOk()
        ->assertSee('₱4,380.00')      // sales: 6 × 730
        ->assertSee('7 reams')         // stock: 4 CR + 3 CB
        ->assertSee('₱4,380.00');      // receivables: nothing confirmed yet
});

test('charts and table widgets render', function () {
    Livewire::test(SalesVsCollectionsChart::class, ['pageFilters' => ['preset' => 'this_year']])->assertOk();
    Livewire::test(SalesVsCollectionsChart::class, ['pageFilters' => ['preset' => 'all']])->set('filter', 'month')->assertOk();
    Livewire::test(SalesByProductChart::class, ['pageFilters' => ['preset' => 'all']])->assertOk();

    Livewire::test(TopResellersByBalance::class)->assertOk()->assertSee('Tito Richie');
    Livewire::test(LowStockProducts::class)->assertOk()->assertSee('CR');   // 4 left, reorder level 5
    Livewire::test(PendingRemittances::class)->assertOk()->assertSee('Tito Richie');
});

test('reseller relation managers render', function () {
    $tito = reseller('Tito Richie');

    foreach ([DistributionsRelationManager::class, RemittancesRelationManager::class, SpecialPricesRelationManager::class] as $manager) {
        Livewire::test($manager, ['ownerRecord' => $tito, 'pageClass' => ViewReseller::class])->assertOk();
    }
});

test('super admins can save app settings', function () {
    $this->actingAs(superAdmin());

    Livewire::test(AppSettings::class)
        ->fillForm(['business_name' => 'JR Paper Supply', 'business_address' => 'San Juan', 'business_contact' => '0917', 'default_reorder_level' => 3])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('business_name'))->toBe('JR Paper Supply')
        ->and(Setting::get('default_reorder_level'))->toBe('3');
});
