<?php

use App\Services\DistributionService;
use App\Services\PurchaseService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedCatalog();
    $this->stock = app(StockService::class);
    $this->admin = admin();
});

test('stock is purchases minus distributions', function () {
    stockUp('CR', 10);
    stockUp('CR', 5);
    distribute('Tito Richie', 'CR', 8, '2026-09-20');

    expect($this->stock->currentStock(product('CR')))->toBe(7)
        ->and($this->stock->stockByProduct()[product('CR')->id])->toBe(7)
        ->and($this->stock->stockByProduct()[product('CB')->id])->toBe(0);
});

test('a distribution that would make stock negative is blocked', function () {
    stockUp('CR', 10);
    distribute('Tito Richie', 'CR', 8, '2026-09-20');

    expect(fn () => distribute('Tito Richie', 'CR', 3, '2026-09-21'))
        ->toThrow(ValidationException::class, 'Not enough stock for CR: only 2 reams available.');

    expect($this->stock->currentStock(product('CR')))->toBe(2);
});

test('multi-line entries check the combined qty per product', function () {
    stockUp('CR', 5);
    stockUp('CB', 5);

    $lines = [
        ['product_id' => product('CR')->id, 'qty' => 3],
        ['product_id' => product('CB')->id, 'qty' => 1],
        ['product_id' => product('CR')->id, 'qty' => 3],
    ];

    expect(fn () => app(DistributionService::class)->createMany(
        ['date' => '2026-09-20', 'reseller_id' => reseller('Tito Richie')->id], $lines, null,
    ))->toThrow(ValidationException::class, 'only 5 reams available');

    // Nothing was saved (atomic).
    expect($this->stock->currentStock(product('CB')))->toBe(5);
});

test('voided rows are excluded from stock', function () {
    $purchase = stockUp('CR', 10);
    stockUp('CR', 4);
    $distribution = distribute('Tito Richie', 'CR', 3, '2026-09-20');

    app(DistributionService::class)->void($distribution, 'Wrong reseller', $this->admin);
    expect($this->stock->currentStock(product('CR')))->toBe(14);

    app(PurchaseService::class)->void($purchase, 'Duplicate entry', $this->admin);
    expect($this->stock->currentStock(product('CR')))->toBe(4);
});

test('voiding a purchase that is already distributed is blocked', function () {
    $purchase = stockUp('CR', 10);
    distribute('Tito Richie', 'CR', 8, '2026-09-20');

    app(PurchaseService::class)->void($purchase, 'Oops', $this->admin);
})->throws(ValidationException::class, 'Not enough stock');

test('editing a distribution adds its own qty back before checking', function () {
    stockUp('CR', 10);
    $distribution = distribute('Tito Richie', 'CR', 8, '2026-09-20');

    app(DistributionService::class)->update($distribution, ['qty' => 10], $this->admin);
    expect($this->stock->currentStock(product('CR')))->toBe(0);

    expect(fn () => app(DistributionService::class)->update($distribution, ['qty' => 11], $this->admin))
        ->toThrow(ValidationException::class, 'only 10 reams available');
});

test('low stock means stock at or below the reorder level', function () {
    // CR reorder level is 5
    stockUp('CR', 6);
    expect($this->stock->isLowStock(product('CR')))->toBeFalse();

    distribute('Tito Richie', 'CR', 1, '2026-09-20');
    expect($this->stock->isLowStock(product('CR')))->toBeTrue();
});
