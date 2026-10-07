<?php

use App\Enums\PriceSource;
use App\Models\PriceRule;
use App\Models\Product;
use App\Services\Pricing\PriceResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedCatalog();
    $this->resolver = app(PriceResolver::class);
});

test('default reseller price follows the effective date', function () {
    $before = $this->resolver->resolve(product('CR'), reseller('Tito Richie'), '2026-09-26');
    $after = $this->resolver->resolve(product('CR'), reseller('Tito Richie'), '2026-09-27');

    expect($before->unitPrice)->toBe('740.00')
        ->and($before->source)->toBe(PriceSource::Default)
        ->and($after->unitPrice)->toBe('730.00')
        ->and($after->source)->toBe(PriceSource::Default)
        ->and($after->rule->effective_date->toDateString())->toBe('2026-09-27');
});

test('special price wins over the default for that reseller only', function () {
    $plaza = $this->resolver->resolve(product('CR'), reseller('Plaza'), '2026-10-01');
    $others = $this->resolver->resolve(product('CR'), reseller('Kariz Store'), '2026-10-01');

    expect($plaza->unitPrice)->toBe('800.00')
        ->and($plaza->source)->toBe(PriceSource::Special)
        ->and($others->unitPrice)->toBe('730.00')
        ->and($others->source)->toBe(PriceSource::Default);

    foreach (['CB' => '850.00', 'PR' => '400.00', 'CC' => '800.00'] as $code => $price) {
        expect($this->resolver->resolve(product($code), reseller('Plaza'), '2026-10-01')->unitPrice)->toBe($price);
    }
});

test('retail customers get the latest default retail price', function () {
    PriceRule::query()->create(['product_id' => product('CR')->id, 'effective_date' => '2026-09-12', 'retail_price' => 850]);
    PriceRule::query()->create(['product_id' => product('CR')->id, 'effective_date' => '2026-10-01', 'retail_price' => 860]);

    $sep = $this->resolver->resolve(product('CR'), reseller('Retail'), '2026-09-20');
    $oct = $this->resolver->resolve(product('CR'), reseller('Retail'), '2026-10-02');

    expect($sep->unitPrice)->toBe('850.00')
        ->and($sep->source)->toBe(PriceSource::Retail)
        ->and($oct->unitPrice)->toBe('860.00');

    // The 730 reseller-price row (Sep 27) has no retail price, so it never blanks retail out.
    expect($this->resolver->resolve(product('CR'), reseller('Retail'), '2026-09-28')->unitPrice)->toBe('850.00');
});

test('retail customers are blocked when no retail price exists', function () {
    $this->resolver->resolve(product('CR'), reseller('Retail'), '2026-09-20');
})->throws(ValidationException::class, 'No retail price is set for CR');

test('a back-dated rule is picked up for dates on or after it', function () {
    PriceRule::query()->create([
        'product_id' => product('CR')->id, 'effective_date' => '2026-09-20', 'reseller_price' => 735,
    ]);

    expect($this->resolver->resolve(product('CR'), reseller('Tito Richie'), '2026-09-19')->unitPrice)->toBe('740.00')
        ->and($this->resolver->resolve(product('CR'), reseller('Tito Richie'), '2026-09-20')->unitPrice)->toBe('735.00')
        ->and($this->resolver->resolve(product('CR'), reseller('Tito Richie'), '2026-09-27')->unitPrice)->toBe('730.00');
});

test('no matching rule is a validation error', function () {
    expect(fn () => $this->resolver->resolve(product('CR'), reseller('Tito Richie'), '2026-09-11'))
        ->toThrow(ValidationException::class, 'No reseller price is set for CR on Sep 11, 2026');

    $new = Product::factory()->create(['code' => 'XX']);

    expect(fn () => $this->resolver->resolve($new, reseller('Tito Richie'), '2026-10-01'))
        ->toThrow(ValidationException::class);
});

test('admins can override the price manually with a reason', function () {
    stockUp('CR', 10);

    $distribution = distribute('Tito Richie', 'CR', 2, '2026-09-20', admin(), [
        'override_price' => true, 'unit_price' => 700, 'override_reason' => 'Bulk discount',
    ]);

    expect($distribution->unit_price)->toBe('700.00')
        ->and($distribution->total_amount)->toBe('1400.00')
        ->and($distribution->price_source)->toBe(PriceSource::Manual)
        ->and($distribution->price_rule_id)->toBeNull()
        ->and($distribution->override_reason)->toBe('Bulk discount');
});

test('a manual override requires a reason', function () {
    stockUp('CR', 10);

    distribute('Tito Richie', 'CR', 2, '2026-09-20', admin(), ['override_price' => true, 'unit_price' => 700]);
})->throws(ValidationException::class, 'A reason is required');

test('staff cannot override prices', function () {
    stockUp('CR', 10);

    distribute('Tito Richie', 'CR', 2, '2026-09-20', staff(), [
        'override_price' => true, 'unit_price' => 1, 'override_reason' => 'Friend',
    ]);
})->throws(AuthorizationException::class);
