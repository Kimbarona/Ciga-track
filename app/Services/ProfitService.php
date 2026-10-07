<?php

namespace App\Services;

use App\Models\Distribution;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Remittance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Weighted-average-cost profit figures (admin / super admin only — never sent to staff).
 *
 *  avg cost   = Σ purchases.total_cost ÷ Σ purchases.qty   (all-time, per product)
 *  COGS       = qty distributed × avg cost
 *  gross      = sales − COGS
 *  net        = gross − expenses
 *  cash       = confirmed remittances − purchases − expenses
 */
class ProfitService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @return Collection<int, float> avg cost keyed by product id
     */
    public function averageCosts(): Collection
    {
        return Purchase::query()->notVoided()
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(total_cost) as cost, SUM(qty) as qty')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id => (int) $row->qty === 0 ? 0.0 : (float) $row->cost / (int) $row->qty,
            ]);
    }

    public function averageCost(Product|int $product): float
    {
        $id = $product instanceof Product ? $product->id : $product;

        return (float) ($this->averageCosts()[$id] ?? 0.0);
    }

    /**
     * Value of current stock at average cost.
     */
    public function stockValue(): float
    {
        $avg = $this->averageCosts();

        return round(
            $this->stock->stockByProduct()->map(fn (int $qty, int $productId) => $qty * ($avg[$productId] ?? 0))->sum(),
            2,
        );
    }

    /**
     * @return array<string, float|int>
     */
    public function summary(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): array
    {
        $byProduct = $this->byProduct($from, $to);

        $sales = round($byProduct->sum('sales'), 2);
        $cogs = round($byProduct->sum('cogs'), 2);
        $expenses = round((float) $this->period(Expense::query()->notVoided(), $from, $to)->sum('amount'), 2);
        $purchases = round((float) $this->period(Purchase::query()->notVoided(), $from, $to)->sum('total_cost'), 2);
        $collections = round((float) $this->period(Remittance::query()->confirmed(), $from, $to)->sum('amount'), 2);
        $gross = round($sales - $cogs, 2);

        return [
            'reams_sold' => (int) $byProduct->sum('qty'),
            'sales' => $sales,
            'cogs' => $cogs,
            'gross_profit' => $gross,
            'expenses' => $expenses,
            'net_profit' => round($gross - $expenses, 2),
            'purchases' => $purchases,
            'collections' => $collections,
            'cash_position' => round($collections - $purchases - $expenses, 2),
        ];
    }

    /**
     * @return Collection<int, array{product_id: int, code: string, qty: int, sales: float, avg_cost: float, cogs: float, gross_profit: float}>
     */
    public function byProduct(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): Collection
    {
        $avg = $this->averageCosts();

        $sold = $this->period(Distribution::query()->notVoided(), $from, $to)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty) as qty, SUM(total_amount) as sales')
            ->get()
            ->keyBy('product_id');

        return Product::withTrashed()->orderBy('code')->get()->map(function (Product $product) use ($avg, $sold) {
            $qty = (int) ($sold[$product->id]->qty ?? 0);
            $sales = (float) ($sold[$product->id]->sales ?? 0);
            $avgCost = (float) ($avg[$product->id] ?? 0);
            $cogs = $qty * $avgCost;

            return [
                'product_id' => $product->id,
                'code' => $product->code,
                'qty' => $qty,
                'sales' => round($sales, 2),
                'avg_cost' => round($avgCost, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($sales - $cogs, 2),
            ];
        });
    }

    private function period(Builder $query, CarbonInterface|string|null $from, CarbonInterface|string|null $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('date', '>=', Carbon::parse($from)->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', Carbon::parse($to)->toDateString()));
    }
}
