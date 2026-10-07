<?php

use App\Filament\Pages\Reports;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Services\RemittanceService;
use App\Services\ReportService;
use Filament\Facades\Filament;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    seedCatalog();
    $admin = admin();
    stockUp('CR', 10, 680, '2026-09-11');
    stockUp('CR', 10, 700, '2026-10-02');
    distribute('Tito Richie', 'CR', 2, '2026-09-20');   // 1,480
    distribute('Tito Richie', 'CR', 3, '2026-10-03');   // 2,190
    app(RemittanceService::class)->record(['date' => '2026-09-25', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 1000, 'mode' => 'cash'], $admin);
    app(RemittanceService::class)->record(['date' => '2026-10-04', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 999, 'mode' => 'cash'], staff()); // pending
    app(ExpenseService::class)->create(['date' => '2026-10-04', 'expense_category_id' => ExpenseCategory::query()->value('id'), 'description' => 'Gas', 'amount' => 100], $admin);
    $this->reports = app(ReportService::class);
});

test('statement has opening balance, running balance and only confirmed remittances', function () {
    $statement = $this->reports->statement(reseller('Tito Richie'), '2026-09-21', '2026-10-31');

    expect($statement['opening'])->toBe(1480.0)
        ->and(array_column($statement['rows'], 'balance'))->toBe([480.0, 2670.0])
        ->and($statement['total_debit'])->toBe(2190.0)
        ->and($statement['total_credit'])->toBe(1000.0)
        ->and($statement['closing'])->toBe(2670.0);
});

test('profit and loss is grouped by month', function () {
    $pnl = $this->reports->profitAndLoss('2026-09-01', '2026-10-31');

    // avg cost CR = (6,800 + 7,000) / 20 = 690
    expect(array_column($pnl['rows'], 'period'))->toBe(['Sep 2026', 'Oct 2026'])
        ->and($pnl['rows'][0]['sales'])->toBe(1480.0)
        ->and($pnl['rows'][0]['cogs'])->toBe(1380.0)
        ->and($pnl['rows'][1]['net_profit'])->toBe(2190.0 - 2070.0 - 100.0)
        ->and($pnl['totals']['sales'])->toBe(3670.0)
        ->and($pnl['totals']['reams_sold'])->toBe(5);
});

test('stock movement reconciles opening + in − out = closing', function () {
    $movement = $this->reports->stockMovement('2026-10-01', '2026-10-31');
    $cr = collect($movement['rows'])->firstWhere('code', 'CR');

    expect($cr)->toMatchArray(['opening' => 8, 'in' => 10, 'out' => 3, 'closing' => 15]);
});

test('report files render as print, pdf and xlsx for admins', function () {
    $this->actingAs(admin());
    $tito = reseller('Tito Richie');

    $this->get(route('admin.reports.statement', ['reseller' => $tito]))
        ->assertOk()->assertSee('Statement of Account')->assertSee('₱2,670.00');
    $this->get(route('admin.reports.pnl', ['preset' => 'all']))->assertOk()->assertSee('Oct 2026');
    $this->get(route('admin.reports.stock'))->assertOk()->assertSee('Stock Movement');

    $pdf = $this->get(route('admin.reports.statement', ['reseller' => $tito, 'format' => 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');

    Excel::fake();
    $this->get(route('admin.reports.pnl', ['format' => 'xlsx']))->assertOk();
    Excel::assertDownloaded('profit-and-loss-'.now()->format('Ymd').'.xlsx');
});

test('staff cannot open reports', function () {
    $this->actingAs(staff());

    $this->get(route('admin.reports.pnl'))->assertForbidden();
    $this->get(route('admin.reports.statement', ['reseller' => reseller('Plaza')]))->assertForbidden();
    $this->get(Reports::getUrl(panel: 'admin'))->assertForbidden();
});

test('the reports page renders for admins', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(admin())->get(Reports::getUrl())->assertOk()->assertSee('Reseller statement');
});
