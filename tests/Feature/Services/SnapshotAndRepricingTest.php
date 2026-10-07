<?php

use App\Enums\PriceSource;
use App\Models\PriceRule;
use App\Services\DistributionService;
use App\Services\Pricing\RepricingService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    seedCatalog();
    stockUp('CR', 100);
    stockUp('CB', 100);
    $this->admin = admin();
    $this->repricer = app(RepricingService::class);
});

test('the resolved price is snapshotted on the distribution', function () {
    $distribution = distribute('Tito Richie', 'CR', 3, '2026-09-20');

    expect($distribution->unit_price)->toBe('740.00')
        ->and($distribution->total_amount)->toBe('2220.00')
        ->and($distribution->price_source)->toBe(PriceSource::Default)
        ->and($distribution->price_rule_id)->not->toBeNull();
});

test('editing or adding price rules does not change existing distributions', function () {
    $distribution = distribute('Tito Richie', 'CR', 3, '2026-09-20');

    // Edit the rule the distribution used, and add a back-dated rule that would now win.
    PriceRule::query()->find($distribution->price_rule_id)->update(['reseller_price' => 999]);
    PriceRule::query()->create(['product_id' => product('CR')->id, 'effective_date' => '2026-09-15', 'reseller_price' => 700]);

    $distribution->refresh();

    expect($distribution->unit_price)->toBe('740.00')
        ->and($distribution->total_amount)->toBe('2220.00');
});

test('editing only the qty keeps the snapshotted price', function () {
    $distribution = distribute('Tito Richie', 'CR', 3, '2026-09-20');
    PriceRule::query()->create(['product_id' => product('CR')->id, 'effective_date' => '2026-09-15', 'reseller_price' => 700]);

    app(DistributionService::class)->update($distribution, ['qty' => 5], $this->admin);

    expect($distribution->fresh()->unit_price)->toBe('740.00')
        ->and($distribution->fresh()->total_amount)->toBe('3700.00');
});

test('re-price preview lists only the rows the new rule now wins', function () {
    $before = distribute('Tito Richie', 'CR', 1, '2026-09-14');      // before effective date
    $hit = distribute('Tito Richie', 'CR', 4, '2026-09-20');         // affected
    $hit2 = distribute('Kariz Store', 'CR', 2, '2026-09-25');        // affected (default scope = all resellers)
    $later = distribute('Tito Richie', 'CR', 1, '2026-09-28');       // Sep 27 rule (730) still wins
    $plaza = distribute('Plaza', 'CR', 1, '2026-09-20');             // special price wins
    $otherProduct = distribute('Tito Richie', 'CB', 1, '2026-09-20');
    $voided = distribute('Tito Richie', 'CR', 1, '2026-09-21');
    app(DistributionService::class)->void($voided, 'Mistake', $this->admin);
    $manual = distribute('Tito Richie', 'CR', 1, '2026-09-22', $this->admin, [
        'override_price' => true, 'unit_price' => 600, 'override_reason' => 'Promo',
    ]);

    $rule = PriceRule::query()->create([
        'product_id' => product('CR')->id, 'effective_date' => '2026-09-18', 'reseller_price' => 745,
    ]);

    $preview = $this->repricer->preview($rule);

    expect($preview->pluck('distribution.id')->all())->toBe([$hit->id, $hit2->id]);

    $first = $preview->first();
    expect($first['old_price'])->toBe(740.0)
        ->and($first['new_price'])->toBe(745.0)
        ->and($first['old_total'])->toBe(2960.0)
        ->and($first['new_total'])->toBe(2980.0)
        ->and($first['difference'])->toBe(20.0);

    // Nothing changes until the admin confirms.
    expect($hit->fresh()->unit_price)->toBe('740.00');

    $count = $this->repricer->apply($rule, $this->admin);

    expect($count)->toBe(2)
        ->and($hit->fresh()->unit_price)->toBe('745.00')
        ->and($hit->fresh()->total_amount)->toBe('2980.00')
        ->and($hit->fresh()->price_rule_id)->toBe($rule->id)
        ->and($hit2->fresh()->total_amount)->toBe('1490.00');

    foreach ([$before, $later, $plaza, $otherProduct, $voided, $manual] as $untouched) {
        expect($untouched->fresh()->unit_price)->toBe($untouched->unit_price);
    }

    $log = Activity::query()->where('event', 'repriced')->sole();
    expect($log->causer_id)->toBe($this->admin->id)
        ->and($log->properties['count'])->toBe(2)
        ->and($log->properties['total_difference'])->toEqual(30);

    // Applying again is a no-op.
    expect($this->repricer->preview($rule))->toBeEmpty();
});

test('a back-dated special price only re-prices that reseller', function () {
    $plaza = distribute('Plaza', 'CR', 2, '2026-09-20');
    $tito = distribute('Tito Richie', 'CR', 2, '2026-09-20');

    $rule = PriceRule::query()->create([
        'product_id' => product('CR')->id, 'reseller_id' => reseller('Plaza')->id,
        'effective_date' => '2026-09-15', 'reseller_price' => 790,
    ]);

    $preview = $this->repricer->preview($rule);
    expect($preview->pluck('distribution.id')->all())->toBe([$plaza->id]);

    $this->repricer->apply($rule, $this->admin);

    expect($plaza->fresh()->unit_price)->toBe('790.00')
        ->and($plaza->fresh()->price_source)->toBe(PriceSource::Special)
        ->and($tito->fresh()->unit_price)->toBe('740.00');
});

test('a price rule used by a distribution is locked for editing', function () {
    $distribution = distribute('Tito Richie', 'CR', 1, '2026-09-20');
    $usedRule = PriceRule::query()->find($distribution->price_rule_id);
    $unusedRule = PriceRule::query()->create(['product_id' => product('PR')->id, 'effective_date' => '2026-12-01', 'reseller_price' => 360]);

    expect($this->admin->can('update', $usedRule))->toBeFalse()
        ->and($this->admin->can('update', $unusedRule))->toBeTrue()
        ->and(staff()->can('update', $unusedRule))->toBeFalse();
});
