<?php

namespace App\Services;

use App\Models\Distribution;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Current stock = Σ purchases.qty − Σ distributions.qty (voided rows excluded).
 */
class StockService
{
    public function currentStock(Product|int $product): int
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return $this->purchasedQty($productId) - $this->distributedQty($productId);
    }

    /**
     * Stock per product id for every product (including ones with no movement).
     *
     * @return Collection<int, int>
     */
    public function stockByProduct(): Collection
    {
        $purchased = Purchase::query()->notVoided()
            ->groupBy('product_id')->selectRaw('product_id, SUM(qty) as qty')->pluck('qty', 'product_id');
        $distributed = Distribution::query()->notVoided()
            ->groupBy('product_id')->selectRaw('product_id, SUM(qty) as qty')->pluck('qty', 'product_id');

        return Product::withTrashed()->pluck('id')->mapWithKeys(fn (int $id) => [
            $id => (int) ($purchased[$id] ?? 0) - (int) ($distributed[$id] ?? 0),
        ]);
    }

    public function isLowStock(Product $product, ?int $stock = null): bool
    {
        return ($stock ?? $this->currentStock($product)) <= $product->reorder_level;
    }

    /**
     * Block an outgoing movement that would make stock negative.
     *
     * @param  int  $alreadyCounted  qty of the row being edited (it is added back first)
     */
    public function assertCanRemove(Product $product, int $qty, int $alreadyCounted = 0, string $field = 'qty'): void
    {
        $available = $this->currentStock($product) + $alreadyCounted;

        if ($qty > $available) {
            throw ValidationException::withMessages([
                $field => sprintf(
                    'Not enough stock for %s: only %d %s available.',
                    $product->code,
                    max($available, 0),
                    $available === 1 ? 'ream' : 'reams',
                ),
            ]);
        }
    }

    private function purchasedQty(int $productId): int
    {
        return (int) Purchase::query()->notVoided()->where('product_id', $productId)->sum('qty');
    }

    private function distributedQty(int $productId): int
    {
        return (int) Distribution::query()->notVoided()->where('product_id', $productId)->sum('qty');
    }
}
