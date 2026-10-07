<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\Setting;
use App\Services\ReportService;
use App\Support\Period;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable / PDF / Excel versions of the admin reports.
 * ?format=print (default) | pdf | xlsx, plus preset / from / to for the period.
 */
class ReportController extends Controller
{
    private const MONEY = '"₱"#,##0.00;-"₱"#,##0.00';

    public function __construct(private readonly ReportService $reports) {}

    public function statement(Request $request, Reseller $reseller): Response
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->statement($reseller, $from, $to);
        $title = "Statement of Account — {$reseller->name}";

        return $this->respond($request, 'reports.statement', $data, $title, "statement-{$reseller->name}", fn () => new ReportExport(
            'Statement',
            [$title, $this->business(), 'Period: '.$this->periodLabel($request), 'Opening balance: '.number_format($data['opening'], 2)],
            ['Date', 'Description', 'Charges', 'Payments', 'Balance'],
            array_map(fn ($r) => [$r['date'], $r['description'], $r['debit'] ?: null, $r['credit'] ?: null, $r['balance']], $data['rows']),
            ['', 'Totals / closing balance', $data['total_debit'], $data['total_credit'], $data['closing']],
            ['C' => self::MONEY, 'D' => self::MONEY, 'E' => self::MONEY],
        ));
    }

    public function profitAndLoss(Request $request): Response
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->profitAndLoss($from, $to);
        $title = 'Profit & Loss';

        $row = fn (array $r, string $label) => [$label, $r['reams_sold'], $r['sales'], $r['cogs'], $r['gross_profit'], $r['expenses'], $r['net_profit'], $r['collections'], $r['purchases'], $r['cash_position']];

        return $this->respond($request, 'reports.pnl', $data, $title, 'profit-and-loss', fn () => new ReportExport(
            'Profit & Loss',
            [$title, $this->business(), 'Period: '.$this->periodLabel($request), 'COGS uses weighted average cost per product.'],
            ['Period', 'Reams sold', 'Sales', 'COGS', 'Gross profit', 'Expenses', 'Net profit', 'Collections', 'Purchases', 'Cash position'],
            array_map(fn ($r) => $row($r, $r['period']), $data['rows']),
            $row($data['totals'], 'Total'),
            collect(range('C', 'J'))->mapWithKeys(fn ($c) => [$c => self::MONEY])->all(),
        ));
    }

    public function stockMovement(Request $request): Response
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->stockMovement($from, $to);
        $title = 'Stock Movement';

        return $this->respond($request, 'reports.stock', $data, $title, 'stock-movement', fn () => new ReportExport(
            'Stock Movement',
            [$title, $this->business(), 'Period: '.$this->periodLabel($request)],
            ['Code', 'Product', 'Opening', 'In (purchases)', 'Out (distributions)', 'Closing'],
            array_map(fn ($r) => [$r['code'], $r['name'], $r['opening'], $r['in'], $r['out'], $r['closing']], $data['rows']),
            ['', 'Total', ...array_values($data['totals'])],
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(Request $request, string $view, array $data, string $title, string $file, callable $export): Response
    {
        $file = str($file)->slug().'-'.now()->format('Ymd');
        $viewData = [
            ...$data,
            'title' => $title,
            'periodLabel' => $this->periodLabel($request),
            'business' => [
                'name' => Setting::businessName(),
                'address' => Setting::get('business_address'),
                'contact' => Setting::get('business_contact'),
            ],
            'generatedAt' => now()->format('M d, Y g:i A'),
        ];

        return match ($request->query('format')) {
            'pdf' => Pdf::loadView($view, [...$viewData, 'pdf' => true])->setPaper('a4')->download("{$file}.pdf"),
            'xlsx' => Excel::download($export(), "{$file}.xlsx"),
            default => response()->view($view, [...$viewData, 'pdf' => false]),
        };
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $request->validate([
            'preset' => ['nullable', 'in:'.implode(',', array_keys(Period::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return Period::range($request->query('preset', 'all'), $request->query('from'), $request->query('to'));
    }

    private function periodLabel(Request $request): string
    {
        return Period::label($request->query('preset', 'all'), $request->query('from'), $request->query('to'));
    }

    private function business(): string
    {
        return trim(Setting::businessName().' '.Setting::get('business_address'));
    }
}
