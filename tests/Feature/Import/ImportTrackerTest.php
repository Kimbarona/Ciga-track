<?php

use App\Enums\RemittanceStatus;
use App\Models\Distribution;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Remittance;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use App\Services\Pricing\PriceResolver;
use App\Services\ProfitService;
use App\Services\StockService;

/*
 * Acceptance check for `import:tracker`, using tests/fixtures/tracker_fixture.xlsx
 * (the tracker's data + SAMPLE rows that must be skipped).
 *
 * NOTE: the build prompt listed Tito Richie as 59 reams / ₱43,570 / ₱29,660 / ₱13,910.
 * The workbook delivered on Oct 07, 2026 contains one more CB ream (Oct 05) and the
 * ₱3,300 remittance of Oct 07, and its own Resellers sheet shows 60 / ₱44,370 / ₱32,960 /
 * ₱11,410 — the import matches the workbook exactly.
 */
const FIXTURE = __DIR__.'/../../fixtures/tracker_fixture.xlsx';

function importFixture(array $options = []): void
{
    $command = test()->artisan('import:tracker', ['path' => FIXTURE, ...$options]);

    if ($options['--fresh'] ?? false) {
        $command->expectsConfirmation(
            'This deletes ALL distributions, remittances, purchases and expenses before importing. Continue?',
            'yes',
        );
    }

    $command->assertSuccessful();
}

test('Tito Richie matches the tracker', function () {
    importFixture();

    $tito = app(BalanceService::class)->forReseller(Reseller::query()->where('name', 'Tito Richie')->sole());

    expect($tito->reamsTaken)->toBe(60)
        ->and($tito->totalTaken)->toBe(44370.0)
        ->and($tito->confirmedRemitted)->toBe(32960.0)
        ->and($tito->balance())->toBe(11410.0)
        ->and($tito->status()->getLabel())->toBe('May Balance');
});

test('CR is ₱740 before Sep 27, 2026 and ₱730 from Sep 27', function () {
    importFixture();

    $cr = product('CR');
    $before = Distribution::query()->where('product_id', $cr->id)->whereDate('date', '<', '2026-09-27')->pluck('unit_price')->unique()->values()->all();
    $after = Distribution::query()->where('product_id', $cr->id)->whereDate('date', '>=', '2026-09-27')->pluck('unit_price')->unique()->values()->all();

    expect($before)->toBe(['740.00'])->and($after)->toBe(['730.00']);
});

test('Plaza gets its special prices', function () {
    importFixture();

    $resolver = app(PriceResolver::class);

    foreach (['CR' => '800.00', 'CB' => '850.00', 'PR' => '400.00', 'CC' => '800.00'] as $code => $price) {
        expect($resolver->resolve(product($code), reseller('Plaza'), '2026-10-01')->unitPrice)->toBe($price);
    }
});

test('totals match the tracker dashboard', function () {
    importFixture();

    $summary = app(ProfitService::class)->summary();

    expect($summary)->toMatchArray([
        'sales' => 44370.0,
        'purchases' => 83600.0,
        'collections' => 32960.0,
        'expenses' => 1600.0,
        'gross_profit' => 3117.65,
        'net_profit' => 1517.65,
        'cash_position' => -52240.0,
    ])
        ->and(app(StockService::class)->stockByProduct()->sum())->toBe(68)
        ->and(app(ProfitService::class)->stockValue())->toBe(42347.65);
});

test('sample rows, the sample reseller and blank rows are skipped', function () {
    importFixture();

    expect(Purchase::query()->count())->toBe(14)
        ->and(Distribution::query()->count())->toBe(17)
        ->and(Remittance::query()->count())->toBe(9)
        ->and(Expense::query()->count())->toBe(5)
        ->and(Reseller::query()->where('name', 'Sample Reseller')->exists())->toBeFalse()
        ->and(Reseller::query()->where('name', 'Retail')->value('type')->value)->toBe('retail');
});

test('imported remittances are confirmed', function () {
    importFixture();

    expect(Remittance::query()->where('status', '!=', RemittanceStatus::Confirmed)->count())->toBe(0);
});

test('the import is idempotent and --fresh rebuilds transactions', function () {
    importFixture();
    importFixture();

    expect(Distribution::query()->count())->toBe(17)
        ->and(Purchase::query()->count())->toBe(14);

    // Something entered in the app, then a fresh re-import wipes transactions first.
    distribute('Tito Richie', 'CR', 1, '2026-10-07');
    expect(Distribution::query()->count())->toBe(18);

    importFixture(['--fresh' => true]);

    expect(Distribution::query()->count())->toBe(17)
        ->and(Remittance::query()->count())->toBe(9);
});

test('the summary lists counts and skipped rows', function () {
    $this->artisan('import:tracker', ['path' => FIXTURE])
        ->expectsOutputToContain('row(s) skipped')
        ->expectsOutputToContain('Sample row')
        ->expectsOutputToContain('Tito Richie')
        ->assertSuccessful();
});

test('a missing file fails without saving anything', function () {
    $this->artisan('import:tracker', ['path' => 'nope.xlsx'])->assertFailed();

    expect(Distribution::query()->count())->toBe(0);
});
