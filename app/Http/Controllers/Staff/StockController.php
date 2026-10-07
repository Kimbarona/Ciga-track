<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProfitService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __invoke(Request $request, StockService $stock, ProfitService $profit): Response
    {
        $showCosts = $request->user()->can('view-financials');

        return Inertia::render('stocks', [
            'showCosts' => $showCosts,
            // Deferred so the page shell (and skeletons) render instantly.
            'products' => Inertia::defer(function () use ($stock, $profit, $showCosts) {
                $avg = $showCosts ? $profit->averageCosts() : collect();

                return $stock->withStock(Product::query()->active())->orderBy('code')->get()
                    ->map(function (Product $p) use ($avg, $showCosts) {
                        $row = [
                            'id' => $p->id,
                            'code' => $p->code,
                            'name' => $p->name,
                            'stock' => (int) $p->current_stock,
                            'reorder_level' => $p->reorder_level,
                            'is_low' => (int) $p->current_stock <= $p->reorder_level,
                        ];

                        // Cost data only for admins — never serialized for staff.
                        if ($showCosts) {
                            $row['avg_cost'] = round((float) ($avg[$p->id] ?? 0), 2);
                            $row['stock_value'] = round((int) $p->current_stock * (float) ($avg[$p->id] ?? 0), 2);
                        }

                        return $row;
                    })
                    ->all();
            }),
        ]);
    }
}
