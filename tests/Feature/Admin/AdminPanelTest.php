<?php

use App\Enums\PriceSource;
use App\Enums\RemittanceStatus;
use App\Filament\Pages\AppSettings;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Distributions\DistributionResource;
use App\Filament\Resources\Distributions\Pages\CreateDistribution;
use App\Filament\Resources\Distributions\Pages\ListDistributions;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\PriceRules\Pages\ListPriceRules;
use App\Filament\Resources\PriceRules\PriceRuleResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Remittances\Pages\ListRemittances;
use App\Filament\Resources\Remittances\RemittanceResource;
use App\Filament\Resources\Resellers\ResellerResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Distribution;
use App\Models\PriceRule;
use App\Services\RemittanceService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    seedCatalog();
    stockUp('CR', 20);
    Filament::setCurrentPanel('admin');
});

test('guests are sent to the shared login page', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('staff cannot access the admin panel', function () {
    $this->actingAs(staff())->get('/admin')->assertForbidden();
    $this->actingAs(staff())->get(DistributionResource::getUrl('index'))->assertForbidden();
});

test('admins and super admins can open every admin page', function ($role) {
    $user = $role === 'super' ? superAdmin() : admin();
    distribute('Tito Richie', 'CR', 2, '2026-09-20');

    $this->actingAs($user);

    $urls = [
        '/admin',
        DistributionResource::getUrl('index'), DistributionResource::getUrl('create'),
        RemittanceResource::getUrl('index'), RemittanceResource::getUrl('create'),
        PurchaseResource::getUrl('index'), PurchaseResource::getUrl('create'),
        ExpenseResource::getUrl('index'), ExpenseResource::getUrl('create'),
        ProductResource::getUrl('index'), ProductResource::getUrl('create'),
        ResellerResource::getUrl('index'), ResellerResource::getUrl('view', ['record' => reseller('Tito Richie')]),
        PriceRuleResource::getUrl('index'), PriceRuleResource::getUrl('create'),
        UserResource::getUrl('index'), UserResource::getUrl('create'),
        AuditLogResource::getUrl('index'),
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
})->with(['admin', 'super']);

test('app settings are for super admins only', function () {
    $this->actingAs(admin())->get(AppSettings::getUrl())->assertForbidden();
    $this->actingAs(superAdmin())->get(AppSettings::getUrl())->assertOk();
});

test('creating a distribution in the admin snapshots the resolved price', function () {
    $this->actingAs(admin());

    Livewire::test(CreateDistribution::class)
        ->fillForm([
            'date' => '2026-09-20',
            'reseller_id' => reseller('Plaza')->id,
            'product_id' => product('CR')->id,
            'qty' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $distribution = Distribution::query()->sole();
    expect($distribution->unit_price)->toBe('800.00')
        ->and($distribution->price_source)->toBe(PriceSource::Special)
        ->and($distribution->total_amount)->toBe('2400.00');
});

test('the admin form blocks negative stock with an error on qty', function () {
    $this->actingAs(admin());

    Livewire::test(CreateDistribution::class)
        ->fillForm([
            'date' => '2026-09-20',
            'reseller_id' => reseller('Tito Richie')->id,
            'product_id' => product('CR')->id,
            'qty' => 21,
        ])
        ->call('create')
        ->assertHasFormErrors(['qty']);

    expect(Distribution::query()->count())->toBe(0);
});

test('admins can override the price in the admin form', function () {
    $this->actingAs(admin());

    Livewire::test(CreateDistribution::class)
        ->fillForm([
            'date' => '2026-09-20',
            'reseller_id' => reseller('Tito Richie')->id,
            'product_id' => product('CR')->id,
            'qty' => 1,
            'override_price' => true,
            'unit_price' => 699,
            'override_reason' => 'Damaged ream',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Distribution::query()->sole()->price_source)->toBe(PriceSource::Manual);
});

test('void action requires a reason and excludes the row', function () {
    $this->actingAs(admin());
    $distribution = distribute('Tito Richie', 'CR', 2, '2026-09-20');

    Livewire::test(ListDistributions::class)
        ->callTableAction('void', $distribution, data: ['reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    Livewire::test(ListDistributions::class)
        ->callTableAction('void', $distribution, data: ['reason' => 'Wrong reseller']);

    expect($distribution->fresh()->isVoided())->toBeTrue()
        ->and($distribution->fresh()->void_reason)->toBe('Wrong reseller');
});

test('pending remittances can be confirmed from the table', function () {
    $this->actingAs(admin());
    $remittance = app(RemittanceService::class)->record(
        ['date' => '2026-09-20', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 500, 'mode' => 'cash'], staff(),
    );

    expect(RemittanceResource::getNavigationBadge())->toBe('1');

    Livewire::test(ListRemittances::class)
        ->assertCanSeeTableRecords([$remittance])
        ->callTableAction('confirm', $remittance);

    expect($remittance->fresh()->status)->toBe(RemittanceStatus::Confirmed)
        ->and(RemittanceResource::getNavigationBadge())->toBeNull();
});

test('the re-price action applies a back-dated rule', function () {
    $this->actingAs(admin());
    $distribution = distribute('Tito Richie', 'CR', 2, '2026-09-20');

    $rule = PriceRule::query()->create([
        'product_id' => product('CR')->id, 'effective_date' => '2026-09-15', 'reseller_price' => 750,
    ]);

    Livewire::test(ListPriceRules::class)->callTableAction('reprice', $rule);

    expect($distribution->fresh()->unit_price)->toBe('750.00');
});

test('admins only see staff in user management', function () {
    $admin = admin();
    $staff = staff();
    $super = superAdmin();
    $otherAdmin = admin();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$staff])
        ->assertCanNotSeeTableRecords([$super, $otherAdmin, $admin]);

    $this->get(UserResource::getUrl('edit', ['record' => $super]))->assertNotFound();
});
