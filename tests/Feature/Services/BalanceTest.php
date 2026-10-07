<?php

use App\Enums\BalanceStatus;
use App\Enums\RemittanceStatus;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use App\Services\DistributionService;
use App\Services\RemittanceService;

beforeEach(function () {
    seedCatalog();
    stockUp('CR', 50);
    stockUp('CB', 50);
    $this->balances = app(BalanceService::class);
    $this->remittances = app(RemittanceService::class);
    $this->admin = admin();
    $this->staff = staff();
});

function remit(string $reseller, float $amount, $actor, string $date = '2026-09-25')
{
    return app(RemittanceService::class)->record([
        'date' => $date, 'reseller_id' => reseller($reseller)->id, 'amount' => $amount, 'mode' => 'cash',
    ], $actor);
}

test('only confirmed remittances reduce the balance', function () {
    distribute('Tito Richie', 'CR', 2, '2026-09-20');   // 1,480
    distribute('Tito Richie', 'CB', 1, '2026-09-20');   //   810
    remit('Tito Richie', 1000, $this->admin);            // confirmed
    $pending = remit('Tito Richie', 500, $this->staff);  // pending
    $rejected = remit('Tito Richie', 300, $this->staff);
    $this->remittances->reject($rejected, 'Not received', $this->admin);

    $balance = $this->balances->forReseller(reseller('Tito Richie'));

    expect($balance->reamsTaken)->toBe(3)
        ->and($balance->totalTaken)->toBe(2290.0)
        ->and($balance->confirmedRemitted)->toBe(1000.0)
        ->and($balance->pendingRemitted)->toBe(500.0)
        ->and($balance->balance())->toBe(1290.0)
        ->and($balance->status())->toBe(BalanceStatus::MayBalance);

    $this->remittances->confirm($pending, $this->admin);

    expect($this->balances->forReseller(reseller('Tito Richie'))->balance())->toBe(790.0);
});

test('balance statuses: May Balance, Paid, Overpaid', function () {
    distribute('Tito Richie', 'CR', 1, '2026-09-20');   // 740
    distribute('Kariz Store', 'CR', 1, '2026-09-20');   // 740

    remit('Tito Richie', 740, $this->admin);
    remit('Kariz Store', 1000, $this->admin);

    expect($this->balances->forReseller(reseller('Tito Richie'))->status())->toBe(BalanceStatus::Paid)
        ->and($this->balances->forReseller(reseller('Kariz Store'))->status())->toBe(BalanceStatus::Overpaid)
        ->and($this->balances->forReseller(reseller('Kariz Store'))->balance())->toBe(-260.0)
        ->and($this->balances->forReseller(reseller('Plaza'))->status())->toBe(BalanceStatus::Paid);

    distribute('Plaza', 'CR', 1, '2026-09-20');
    expect($this->balances->forReseller(reseller('Plaza'))->status())->toBe(BalanceStatus::MayBalance);
});

test('voided distributions do not count toward the balance', function () {
    $distribution = distribute('Tito Richie', 'CR', 2, '2026-09-20');
    distribute('Tito Richie', 'CR', 1, '2026-09-21');
    app(DistributionService::class)->void($distribution, 'Duplicate', $this->admin);

    expect($this->balances->forReseller(reseller('Tito Richie'))->balance())->toBe(740.0);
});

test('reseller lists get balance totals from SQL', function () {
    distribute('Tito Richie', 'CR', 2, '2026-09-20');
    remit('Tito Richie', 500, $this->admin);
    remit('Tito Richie', 100, $this->staff);

    $rows = $this->balances->withTotals(Reseller::query())->orderByDesc('balance')->get()->keyBy('name');

    expect((float) $rows['Tito Richie']->balance)->toBe(980.0)
        ->and((float) $rows['Tito Richie']->pending_remitted)->toBe(100.0)
        ->and((int) $rows['Tito Richie']->reams_taken)->toBe(2)
        ->and((float) $rows['Plaza']->balance)->toBe(0.0)
        ->and($rows->keys()->first())->toBe('Tito Richie');
});

test('the ledger has a running balance and ignores unconfirmed remittances', function () {
    distribute('Tito Richie', 'CR', 2, '2026-09-20');            // +1480
    remit('Tito Richie', 1000, $this->admin, '2026-09-21');      // -1000
    remit('Tito Richie', 200, $this->staff, '2026-09-22');       // pending, no effect
    distribute('Tito Richie', 'CR', 1, '2026-09-28');            // +730

    $ledger = $this->balances->ledger(reseller('Tito Richie'), includeUnconfirmed: true);

    expect($ledger['entries']->pluck('balance')->all())->toBe([1480.0, 480.0, 480.0, 1210.0])
        ->and($ledger['closing'])->toBe(1210.0)
        ->and($ledger['entries'][2]['status'])->toBe(RemittanceStatus::Pending->value);

    $fromSep21 = $this->balances->ledger(reseller('Tito Richie'), from: '2026-09-21');
    expect($fromSep21['opening'])->toBe(1480.0)
        ->and($fromSep21['entries'])->toHaveCount(2)
        ->and($fromSep21['closing'])->toBe(1210.0);
});
