<?php

namespace App\Services;

use App\Models\Distribution;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Remittance;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data for the admin reports (statement, P&L by period, stock movement).
 * Every report returns plain arrays so the same data feeds the screen, print, PDF and Excel.
 */
class ReportService
{
    public function __construct(
        private readonly BalanceService $balances,
        private readonly ProfitService $profit,
    ) {}

    /**
     * Reseller statement: confirmed remittances only, with a running balance.
     *
     * @return array{reseller: Reseller, from: ?CarbonImmutable, to: ?CarbonImmutable, opening: float, closing: float, total_debit: float, total_credit: float, rows: list<array<string, mixed>>}
     */
    public function statement(Reseller $reseller, CarbonInterface|string|null $from, CarbonInterface|string|null $to): array
    {
        $ledger = $this->balances->ledger($reseller, $from, $to);

        $rows = $ledger['entries']->map(fn (array $e) => [
            'date' => $e['date']->format('M d, Y'),
            'description' => $e['description'],
            'debit' => $e['debit'],
            'credit' => $e['credit'],
            'balance' => $e['balance'],
        ])->values()->all();

        return [
            'reseller' => $reseller,
            'from' => $from ? CarbonImmutable::parse($from) : null,
            'to' => $to ? CarbonImmutable::parse($to) : null,
            'opening' => $ledger['opening'],
            'closing' => $ledger['closing'],
            'total_debit' => round(array_sum(array_column($rows, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($rows, 'credit')), 2),
            'rows' => $rows,
        ];
    }

    /**
     * Profit & loss grouped by month.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function profitAndLoss(CarbonInterface|string|null $from, CarbonInterface|string|null $to): array
    {
        [$start, $end] = $this->bounds($from, $to);

        $rows = [];
        if ($start && $end) {
            for ($month = $start->startOfMonth(); $month <= $end; $month = $month->addMonth()) {
                $periodStart = $month->max($start);
                $periodEnd = $month->endOfMonth()->min($end);
                $rows[] = ['period' => $month->format('M Y'), ...$this->profit->summary($periodStart, $periodEnd)];
            }
        }

        $keys = ['reams_sold', 'sales', 'cogs', 'gross_profit', 'expenses', 'net_profit', 'purchases', 'collections', 'cash_position'];
        $totals = collect($keys)->mapWithKeys(fn ($key) => [$key => round(collect($rows)->sum($key), 2)])->all();
        $totals['reams_sold'] = (int) $totals['reams_sold'];

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Stock movement per product: opening + in − out = closing.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function stockMovement(CarbonInterface|string|null $from, CarbonInterface|string|null $to): array
    {
        $from = $from ? Carbon::parse($from)->toDateString() : null;
        $to = $to ? Carbon::parse($to)->toDateString() : null;

        $sumBy = function ($query, string $op, ?string $date) {
            return $query->when($date, fn ($q) => $q->whereDate('date', $op, $date))
                ->groupBy('product_id')->selectRaw('product_id, SUM(qty) as qty')->pluck('qty', 'product_id');
        };

        $purchasedBefore = $from ? $sumBy(Purchase::query()->notVoided(), '<', $from) : collect();
        $distributedBefore = $from ? $sumBy(Distribution::query()->notVoided(), '<', $from) : collect();

        $inRange = fn ($query) => $query
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->groupBy('product_id')->selectRaw('product_id, SUM(qty) as qty')->pluck('qty', 'product_id');

        $in = $inRange(Purchase::query()->notVoided());
        $out = $inRange(Distribution::query()->notVoided());

        $rows = Product::withTrashed()->orderBy('code')->get()->map(function (Product $p) use ($purchasedBefore, $distributedBefore, $in, $out) {
            $opening = (int) ($purchasedBefore[$p->id] ?? 0) - (int) ($distributedBefore[$p->id] ?? 0);
            $qtyIn = (int) ($in[$p->id] ?? 0);
            $qtyOut = (int) ($out[$p->id] ?? 0);

            return [
                'code' => $p->code,
                'name' => $p->name,
                'opening' => $opening,
                'in' => $qtyIn,
                'out' => $qtyOut,
                'closing' => $opening + $qtyIn - $qtyOut,
            ];
        })->all();

        $totals = collect(['opening', 'in', 'out', 'closing'])
            ->mapWithKeys(fn ($key) => [$key => (int) collect($rows)->sum($key)])->all();

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Resolve an open-ended range to the first/last transaction dates.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function bounds(CarbonInterface|string|null $from, CarbonInterface|string|null $to): array
    {
        $dates = fn (string $agg) => collect([
            Distribution::query()->notVoided()->{$agg}('date'),
            Purchase::query()->notVoided()->{$agg}('date'),
            Expense::query()->notVoided()->{$agg}('date'),
            Remittance::query()->confirmed()->{$agg}('date'),
        ])->filter()->map(fn ($d) => CarbonImmutable::parse($d));

        $start = $from ? CarbonImmutable::parse($from) : $dates('min')->min();
        $end = $to ? CarbonImmutable::parse($to) : ($dates('max')->max() ?? CarbonImmutable::today());

        return [$start?->startOfDay(), $end?->startOfDay()];
    }

    /**
     * @return Collection<int, Reseller>
     */
    public function resellers(): Collection
    {
        return Reseller::withTrashed()->orderBy('name')->get();
    }
}
