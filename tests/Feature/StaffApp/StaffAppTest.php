<?php

use App\Enums\PriceSource;
use App\Enums\RemittanceStatus;
use App\Models\Distribution;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Remittance;
use App\Services\PurchaseService;
use App\Services\RemittanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedCatalog();
    stockUp('CR', 20, 680);
    stockUp('CB', 10, 750);
});

test('every staff page renders for staff', function () {
    $staff = staff();
    $distribution = distribute('Tito Richie', 'CR', 1, today()->toDateString(), $staff);

    $this->actingAs($staff);

    $pages = [
        '/' => 'home',
        '/distributions/new' => 'distributions/create',
        "/distributions/{$distribution->id}/edit" => 'distributions/edit',
        '/remittances/new' => 'remittances/form',
        '/purchases/new' => 'purchases/form',
        '/expenses/new' => 'expenses/form',
        '/stocks' => 'stocks',
        '/resellers' => 'resellers/index',
        '/resellers/'.reseller('Tito Richie')->id => 'resellers/show',
        '/my-entries' => 'my-entries',
        '/profile' => 'profile',
    ];

    foreach ($pages as $url => $component) {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('a multi-line distribution saves every line with snapshotted prices', function () {
    $this->actingAs(staff())
        ->post('/distributions', [
            'date' => '2026-09-28',
            'reseller_id' => reseller('Tito Richie')->id,
            'lines' => [
                ['product_id' => product('CR')->id, 'qty' => 3],
                ['product_id' => product('CB')->id, 'qty' => 2],
            ],
        ])
        ->assertRedirect('/distributions/new')
        ->assertSessionHas('success');

    $rows = Distribution::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->unit_price)->toBe('730.00')
        ->and($rows[1]->unit_price)->toBe('800.00')
        ->and($rows->sum('total_amount'))->toEqual(3790);
});

test('a distribution over the available stock is rejected', function () {
    $this->actingAs(staff())
        ->post('/distributions', [
            'date' => '2026-09-28',
            'reseller_id' => reseller('Tito Richie')->id,
            'lines' => [['product_id' => product('CB')->id, 'qty' => 11]],
        ])
        ->assertSessionHasErrors(['qty' => 'Not enough stock for CB: only 10 reams available.']);

    expect(Distribution::query()->count())->toBe(0);
});

test('staff cannot override prices through the staff app', function () {
    $this->actingAs(staff())
        ->post('/distributions', [
            'date' => '2026-09-28',
            'reseller_id' => reseller('Tito Richie')->id,
            'lines' => [['product_id' => product('CR')->id, 'qty' => 1, 'override_price' => true, 'unit_price' => 1, 'override_reason' => 'x']],
        ])
        ->assertSessionHasErrors(['lines.0.override_price', 'lines.0.unit_price']);

    expect(Distribution::query()->count())->toBe(0);
});

test('admins can override prices through the staff app', function () {
    $this->actingAs(admin())
        ->post('/distributions', [
            'date' => '2026-09-28',
            'reseller_id' => reseller('Tito Richie')->id,
            'lines' => [['product_id' => product('CR')->id, 'qty' => 1, 'override_price' => true, 'unit_price' => 700, 'override_reason' => 'Promo']],
        ])
        ->assertSessionHasNoErrors();

    expect(Distribution::query()->sole()->price_source)->toBe(PriceSource::Manual);
});

test('remittance status depends on who encodes it, never on the request', function () {
    Storage::fake('public');
    $payload = fn () => [
        'date' => '2026-09-28',
        'reseller_id' => reseller('Tito Richie')->id,
        'amount' => 1500,
        'mode' => 'gcash',
        'reference_no' => 'ABC123',
        'status' => 'confirmed', // ignored
        'proof' => UploadedFile::fake()->image('proof.jpg'),
    ];

    $this->actingAs(staff())->post('/remittances', $payload())->assertSessionHasNoErrors();
    $this->actingAs(admin())->post('/remittances', $payload())->assertSessionHasNoErrors();

    [$byStaff, $byAdmin] = Remittance::query()->orderBy('id')->get();

    expect($byStaff->status)->toBe(RemittanceStatus::Pending)
        ->and($byAdmin->status)->toBe(RemittanceStatus::Confirmed)
        ->and($byStaff->proof_path)->not->toBeNull();

    Storage::disk('public')->assertExists($byStaff->proof_path);
});

test('the price lookup returns price, source and available stock', function () {
    $this->actingAs(staff())
        ->getJson('/lookup/price?'.http_build_query(['reseller_id' => reseller('Plaza')->id, 'product_id' => product('CR')->id, 'date' => '2026-09-20']))
        ->assertOk()
        ->assertJson(['unit_price' => 800, 'source' => 'special', 'available' => 20, 'error' => null]);

    $this->actingAs(staff())
        ->getJson('/lookup/price?'.http_build_query(['reseller_id' => reseller('Retail')->id, 'product_id' => product('CR')->id, 'date' => '2026-09-20']))
        ->assertOk()
        ->assertJson(['unit_price' => null]);
});

test('staff can edit their own entries only on the same day', function () {
    $staff = staff();
    $mine = distribute('Tito Richie', 'CR', 1, today()->toDateString(), $staff);
    $theirs = distribute('Tito Richie', 'CR', 1, today()->toDateString(), staff());

    $update = fn (Distribution $d) => $this->actingAs($staff)->put("/distributions/{$d->id}", [
        'date' => today()->toDateString(), 'reseller_id' => $d->reseller_id, 'product_id' => $d->product_id, 'qty' => 2,
    ]);

    $update($mine)->assertRedirect('/my-entries');
    expect($mine->fresh()->qty)->toBe(2);

    $update($theirs)->assertForbidden();

    $this->travel(1)->days();
    $update($mine)->assertForbidden();
    $this->actingAs($staff)->get("/distributions/{$mine->id}/edit")->assertForbidden();
});

test('staff editing a purchase can leave the cost blank to keep it', function () {
    $staff = staff();
    $purchase = app(PurchaseService::class)->create(
        ['date' => today()->toDateString(), 'product_id' => product('PR')->id, 'qty' => 5, 'unit_cost' => 250], $staff,
    );

    $this->actingAs($staff)
        ->get("/purchases/{$purchase->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->missing('purchase.unit_cost'));

    $this->actingAs($staff)->put("/purchases/{$purchase->id}", [
        'date' => today()->toDateString(), 'product_id' => product('PR')->id, 'qty' => 6, 'unit_cost' => null,
    ])->assertSessionHasNoErrors();

    expect($purchase->fresh()->unit_cost)->toBe('250.00')
        ->and($purchase->fresh()->total_cost)->toBe('1500.00');
});

test('expenses can be encoded by staff', function () {
    $this->actingAs(staff())->post('/expenses', [
        'date' => today()->toDateString(),
        'expense_category_id' => ExpenseCategory::query()->where('name', 'Gas/Fare')->value('id'),
        'description' => 'Tricycle',
        'amount' => 60,
        'paid_via' => 'Cash',
    ])->assertSessionHasNoErrors()->assertRedirect('/expenses/new');

    expect(Expense::query()->sole()->amount)->toBe('60.00');
});

test('reseller pages show balances with pending remittances separate', function () {
    $staff = staff();
    distribute('Tito Richie', 'CR', 2, '2026-09-28', $staff);
    app(RemittanceService::class)->record(['date' => '2026-09-29', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 500, 'mode' => 'cash'], $staff);

    $this->actingAs($staff)
        ->get('/resellers')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('resellers')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('resellers.0.name', 'Tito Richie')
                ->where('resellers.0.balance', 1460)
                ->where('resellers.0.pending_remitted', 500)
                ->where('resellers.0.status', 'may_balance')));

    $this->actingAs($staff)
        ->get('/resellers/'.reseller('Tito Richie')->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('balance.balance', 1460)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('ledger.entries', 2)
                ->where('ledger.entries.0.status', 'pending')
                ->where('ledger.closing', 1460)));
});
