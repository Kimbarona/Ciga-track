<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use App\Services\Pricing\PriceResolver;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Small JSON lookups for live previews in the entry forms (no cost data).
 */
class PreviewController extends Controller
{
    public function price(Request $request, PriceResolver $resolver, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'reseller_id' => ['required', 'integer', 'exists:resellers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => ['required', 'date'],
            'ignore_distribution' => ['nullable', 'integer'],
        ]);

        $product = Product::withTrashed()->findOrFail($data['product_id']);
        $available = $stock->currentStock($product);

        if ($ignore = Distribution::query()->notVoided()->find($data['ignore_distribution'] ?? null)) {
            if ($request->user()->can('update', $ignore) && $ignore->product_id === $product->id) {
                $available += $ignore->qty;
            }
        }

        $price = $resolver->find($product, (int) $data['reseller_id'], $data['date']);

        return response()->json([
            'unit_price' => $price ? (float) $price->unitPrice : null,
            'source' => $price?->source->value,
            'source_label' => $price?->source->getLabel(),
            'available' => $available,
            'error' => $price ? null : 'No price is set for '.$product->code.' on this date. Ask an admin to add a price rule.',
        ]);
    }

    public function balance(Reseller $reseller, BalanceService $balances): JsonResponse
    {
        return response()->json($balances->forReseller($reseller)->toArray());
    }
}
