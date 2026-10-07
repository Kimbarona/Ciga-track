<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Reseller;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the unit price for (product, reseller, date):
 *
 *  1. Retail customer  → latest DEFAULT rule's retail_price           (source: retail)
 *  2. Reseller special → latest rule for that reseller's reseller_price (source: special)
 *  3. Otherwise        → latest DEFAULT rule's reseller_price         (source: default)
 *  4. Nothing found    → validation error
 *
 * "Latest" = greatest effective_date <= date (ties: newest row). A rule whose relevant
 * price column is blank is skipped, so a row that only sets a reseller price never
 * blanks out an earlier retail price (and vice versa).
 */
class PriceResolver
{
    public function resolve(Product|int $product, Reseller|int $reseller, CarbonInterface|string $date): ResolvedPrice
    {
        return $this->find($product, $reseller, $date)
            ?? throw ValidationException::withMessages([
                'product_id' => $this->missingMessage($product, $reseller, $date),
            ]);
    }

    public function find(Product|int $product, Reseller|int $reseller, CarbonInterface|string $date): ?ResolvedPrice
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $reseller = $reseller instanceof Reseller ? $reseller : Reseller::withTrashed()->findOrFail($reseller);
        $date = Carbon::parse($date)->toDateString();

        if ($reseller->isRetail()) {
            $rule = $this->latestRule($productId, null, $date, 'retail_price');

            return $rule ? new ResolvedPrice($rule->retail_price, PriceSource::Retail, $rule) : null;
        }

        $special = $this->latestRule($productId, $reseller->id, $date, 'reseller_price');

        if ($special) {
            return new ResolvedPrice($special->reseller_price, PriceSource::Special, $special);
        }

        $default = $this->latestRule($productId, null, $date, 'reseller_price');

        return $default ? new ResolvedPrice($default->reseller_price, PriceSource::Default, $default) : null;
    }

    /**
     * A manual override by an admin (authorization is checked by the caller).
     */
    public function manual(string|float $unitPrice): ResolvedPrice
    {
        return new ResolvedPrice(number_format((float) $unitPrice, 2, '.', ''), PriceSource::Manual, null);
    }

    private function latestRule(int $productId, ?int $resellerId, string $date, string $priceColumn): ?PriceRule
    {
        return PriceRule::query()
            ->where('product_id', $productId)
            ->when(
                $resellerId === null,
                fn ($q) => $q->whereNull('reseller_id'),
                fn ($q) => $q->where('reseller_id', $resellerId),
            )
            ->whereDate('effective_date', '<=', $date)
            ->whereNotNull($priceColumn)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    private function missingMessage(Product|int $product, Reseller $reseller, CarbonInterface|string $date): string
    {
        $code = $product instanceof Product ? $product->code : Product::withTrashed()->find($product)?->code;
        $kind = $reseller->isRetail() ? 'retail' : 'reseller';

        return sprintf(
            'No %s price is set for %s on %s. Add a price rule first.',
            $kind,
            $code ?? 'this product',
            Carbon::parse($date)->format('M d, Y'),
        );
    }
}
