<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Services\ProfitService;
use App\Services\PurchaseService;
use App\Services\RemittanceService;

/*
 * Hand-calculated fixture
 * ------------------------------------------------------------------
 * Purchases  CR 10 @ 680 = 6,800   CR 10 @ 700 = 7,000  → avg 690.00
 *            CB  5 @ 750 = 3,750                         → avg 750.00
 *            (voided CR 100 @ 1 is ignored)
 * Sales      Tito Richie CR 5 @ 740 = 3,700   Plaza CB 2 @ 850 = 1,700  → 5,400
 * COGS       5 × 690 + 2 × 750 = 3,450 + 1,500                          → 4,950
 * Gross      5,400 − 4,950                                              →   450
 * Expenses   100 + 50 (voided 999 ignored)                              →   150
 * Net        450 − 150                                                  →   300
 * Collected  2,000 confirmed (1,000 pending ignored)
 * Purchases  6,800 + 7,000 + 3,750                                      → 17,550
 * Cash       2,000 − 17,550 − 150                                       → −15,700
 * Stock val  CR 15 × 690 + CB 3 × 750 = 10,350 + 2,250                  → 12,600
 */
beforeEach(function () {
    seedCatalog();
    $admin = admin();

    stockUp('CR', 10, 680, '2026-09-11');
    stockUp('CR', 10, 700, '2026-09-15');
    stockUp('CB', 5, 750, '2026-09-11');
    app(PurchaseService::class)->void(stockUp('CR', 100, 1, '2026-09-11'), 'Test row', $admin);

    distribute('Tito Richie', 'CR', 5, '2026-09-20');
    distribute('Plaza', 'CB', 2, '2026-09-20');

    $category = ExpenseCategory::query()->where('name', 'Delivery')->first();
    $expenses = app(ExpenseService::class);
    $expenses->create(['date' => '2026-09-20', 'expense_category_id' => $category->id, 'description' => 'Lalamove', 'amount' => 100], $admin);
    $expenses->create(['date' => '2026-09-21', 'expense_category_id' => $category->id, 'description' => 'Fare', 'amount' => 50], $admin);
    $expenses->void(
        $expenses->create(['date' => '2026-09-21', 'expense_category_id' => $category->id, 'description' => 'Typo', 'amount' => 999], $admin),
        'Typo',
        $admin,
    );

    app(RemittanceService::class)->record(['date' => '2026-09-22', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 2000, 'mode' => 'cash'], $admin);
    app(RemittanceService::class)->record(['date' => '2026-09-22', 'reseller_id' => reseller('Plaza')->id, 'amount' => 1000, 'mode' => 'gcash'], staff());

    $this->profit = app(ProfitService::class);
});

test('weighted average cost per product', function () {
    expect($this->profit->averageCost(product('CR')))->toBe(690.0)
        ->and($this->profit->averageCost(product('CB')))->toBe(750.0)
        ->and($this->profit->averageCost(product('PR')))->toBe(0.0);
});

test('COGS, gross profit, net profit and cash position match the hand calculation', function () {
    expect($this->profit->summary())->toMatchArray([
        'reams_sold' => 7,
        'sales' => 5400.0,
        'cogs' => 4950.0,
        'gross_profit' => 450.0,
        'expenses' => 150.0,
        'net_profit' => 300.0,
        'collections' => 2000.0,
        'purchases' => 17550.0,
        'cash_position' => -15700.0,
    ]);
});

test('stock value is current stock at average cost', function () {
    expect($this->profit->stockValue())->toBe(12600.0);
});

test('per-product breakdown', function () {
    $cr = $this->profit->byProduct()->firstWhere('code', 'CR');

    expect($cr)->toMatchArray(['qty' => 5, 'sales' => 3700.0, 'avg_cost' => 690.0, 'cogs' => 3450.0, 'gross_profit' => 250.0]);
});

test('period filters apply to sales, expenses and collections', function () {
    $sep21 = $this->profit->summary('2026-09-21', '2026-09-30');

    expect($sep21['sales'])->toBe(0.0)
        ->and($sep21['expenses'])->toBe(50.0)
        ->and($sep21['collections'])->toBe(2000.0)
        ->and($sep21['net_profit'])->toBe(-50.0);

    expect(Expense::query()->count())->toBe(3);
});
