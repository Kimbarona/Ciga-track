<?php

use App\Models\ExpenseCategory;
use App\Models\Purchase;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\RemittanceService;

/*
 * "Staff must never see cost, profit, or stock value: hide those numbers in the
 *  API/Inertia props too, not just in the UI."
 *
 * Every staff-reachable GET response — including deferred props and JSON lookups —
 * is scanned for cost/profit keys and for the actual cost figures.
 */
const COST_KEYS = ['avg_cost', 'stock_value', 'unit_cost', 'total_cost', 'cogs', 'gross_profit', 'net_profit', 'cash_position', 'viewFinancials":true'];

beforeEach(function () {
    seedCatalog();
    $this->staff = staff();

    // Distinctive costs so they can be searched for in raw responses.
    stockUp('CR', 20, 683.17);
    $this->purchase = app(PurchaseService::class)->create(
        ['date' => today()->toDateString(), 'product_id' => product('CB')->id, 'qty' => 7, 'unit_cost' => 757.31], $this->staff,
    );
    distribute('Tito Richie', 'CR', 3, today()->toDateString(), $this->staff);
    app(RemittanceService::class)->record(['date' => today()->toDateString(), 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 500, 'mode' => 'cash'], $this->staff);
    app(ExpenseService::class)->create(['date' => today()->toDateString(), 'expense_category_id' => ExpenseCategory::query()->where('name', 'Gas/Fare')->value('id'), 'description' => 'Gas', 'amount' => 50], $this->staff);
});

function staffResponses($test): array
{
    $urls = [
        '/', '/distributions/new', '/remittances/new', '/purchases/new', '/expenses/new',
        '/stocks', '/resellers', '/resellers/'.reseller('Tito Richie')->id,
        '/my-entries?tab=purchases', '/profile',
        '/purchases/'.$test->purchase->id.'/edit',
        '/lookup/price?'.http_build_query(['reseller_id' => reseller('Tito Richie')->id, 'product_id' => product('CR')->id, 'date' => today()->toDateString()]),
        '/lookup/resellers/'.reseller('Tito Richie')->id.'/balance',
    ];

    $bodies = [];

    foreach ($urls as $url) {
        $response = $test->actingAs($test->staff)->get($url)->assertOk();
        $bodies[$url] = $response->getContent();

        // Also load every deferred prop of Inertia pages.
        $page = json_decode(html_entity_decode((string) str($response->getContent())->match('/data-page="([^"]+)"/')), true);

        foreach ($page['deferredProps'] ?? [] as $props) {
            $bodies[$url.' (deferred)'] = $test->actingAs($test->staff)->get($url, [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $page['version'] ?? '',
                'X-Inertia-Partial-Component' => $page['component'],
                'X-Inertia-Partial-Data' => implode(',', $props),
            ])->assertOk()->getContent();
        }
    }

    return $bodies;
}

test('staff never receive cost or profit data in any response', function () {
    foreach (staffResponses($this) as $url => $body) {
        foreach (COST_KEYS as $key) {
            expect(str_contains($body, $key))->toBeFalse("{$url} leaked [{$key}]");
        }

        foreach (['683.17', '757.31', '5301.17'] as $figure) {
            expect(str_contains($body, $figure))->toBeFalse("{$url} leaked cost figure {$figure}");
        }
    }
});

test('admins do receive cost data where it is useful', function () {
    $this->actingAs(admin())
        ->get('/stocks', [])
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload->has('products.0.avg_cost')->has('products.0.stock_value')));

    expect(Purchase::query()->count())->toBe(2);
});
