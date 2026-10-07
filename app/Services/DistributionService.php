<?php

namespace App\Services;

use App\Enums\PriceSource;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\ResolvedPrice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating, editing and voiding distributions (stock out).
 *
 * The unit price is resolved once and SNAPSHOTTED on the row. Editing only the qty or
 * notes keeps the snapshot; changing date / reseller / product re-resolves it.
 */
class DistributionService
{
    public function __construct(
        private readonly PriceResolver $prices,
        private readonly StockService $stock,
    ) {}

    /**
     * Resolve the price for a would-be distribution (used by live previews too).
     *
     * @param  array{override_price?: bool, unit_price?: string|float|null}  $data
     */
    public function priceFor(Product|int $product, Reseller|int $reseller, string $date, array $data = []): ResolvedPrice
    {
        if (! empty($data['override_price'])) {
            return $this->prices->manual($data['unit_price'] ?? 0);
        }

        return $this->prices->resolve($product, $reseller, $date);
    }

    /**
     * @param  array{date: string, reseller_id: int, product_id: int, qty: int, notes?: string|null, override_price?: bool, unit_price?: mixed, override_reason?: string|null}  $data
     */
    public function create(array $data, ?User $actor): Distribution
    {
        return $this->createMany(
            Arr::only($data, ['date', 'reseller_id']),
            [Arr::except($data, ['date', 'reseller_id'])],
            $actor,
        )->first();
    }

    /**
     * Multi-line entry: several products for one reseller on one date, saved atomically.
     *
     * @param  array{date: string, reseller_id: int}  $header
     * @param  list<array{product_id: int, qty: int, notes?: string|null, override_price?: bool, unit_price?: mixed, override_reason?: string|null}>  $lines
     * @return Collection<int, Distribution>
     */
    public function createMany(array $header, array $lines, ?User $actor): Collection
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one product.']);
        }

        return DB::transaction(function () use ($header, $lines, $actor) {
            $reseller = Reseller::query()->findOrFail($header['reseller_id']);
            $date = Carbon::parse($header['date'])->toDateString();
            $products = $this->lockProducts(array_column($lines, 'product_id'));

            // Stock is checked per product against the combined qty of all lines.
            $requested = [];
            foreach ($lines as $line) {
                $requested[$line['product_id']] = ($requested[$line['product_id']] ?? 0) + (int) $line['qty'];
            }
            foreach ($requested as $productId => $qty) {
                $this->stock->assertCanRemove($products[$productId], $qty, field: count($lines) > 1 ? 'lines' : 'qty');
            }

            return collect($lines)->values()->map(function (array $line, int $index) use ($reseller, $date, $products, $actor, $lines) {
                $this->authorizeOverride($line, $actor);

                try {
                    $price = $this->priceFor($products[$line['product_id']], $reseller, $date, $line);
                } catch (ValidationException $e) {
                    throw count($lines) > 1
                        ? ValidationException::withMessages(["lines.{$index}.product_id" => $e->errors()['product_id'] ?? $e->getMessage()])
                        : $e;
                }

                $distribution = new Distribution([
                    'date' => $date,
                    'reseller_id' => $reseller->id,
                    'product_id' => $line['product_id'],
                    'qty' => (int) $line['qty'],
                    'notes' => $line['notes'] ?? null,
                    'override_reason' => $price->source === PriceSource::Manual ? $line['override_reason'] : null,
                    'created_by' => $actor?->id,
                    ...$price->toArray(),
                ]);
                $distribution->save();

                return $distribution;
            });
        });
    }

    /**
     * @param  array{date?: string, reseller_id?: int, product_id?: int, qty?: int, notes?: string|null, override_price?: bool, unit_price?: mixed, override_reason?: string|null}  $data
     */
    public function update(Distribution $distribution, array $data, ?User $actor): Distribution
    {
        if ($distribution->isVoided()) {
            throw ValidationException::withMessages(['distribution' => 'A voided distribution cannot be edited.']);
        }

        return DB::transaction(function () use ($distribution, $data, $actor) {
            $originalProductId = $distribution->product_id;
            $originalQty = $distribution->qty;

            $distribution->fill(Arr::only($data, ['date', 'reseller_id', 'product_id', 'qty', 'notes']));
            $product = $this->lockProducts([$distribution->product_id])[$distribution->product_id];

            $this->stock->assertCanRemove(
                $product,
                $distribution->qty,
                alreadyCounted: $originalProductId === $distribution->product_id ? $originalQty : 0,
            );

            $wasManual = $distribution->price_source === PriceSource::Manual;
            $wantsOverride = array_key_exists('override_price', $data) ? (bool) $data['override_price'] : $wasManual;
            $keyChanged = $distribution->isDirty(['date', 'reseller_id', 'product_id']);

            if ($wantsOverride) {
                $line = [
                    'override_price' => true,
                    'unit_price' => $data['unit_price'] ?? $distribution->unit_price,
                    'override_reason' => $data['override_reason'] ?? $distribution->override_reason,
                ];
                $changesManualPrice = ! $wasManual
                    || (isset($data['unit_price']) && (float) $data['unit_price'] !== (float) $distribution->unit_price)
                    || (isset($data['override_reason']) && $data['override_reason'] !== $distribution->override_reason);

                if ($changesManualPrice) {
                    $this->authorizeOverride($line, $actor);
                }

                $distribution->fill([...$this->prices->manual($line['unit_price'])->toArray(), 'override_reason' => $line['override_reason']]);
            } elseif ($keyChanged || $wasManual) {
                $price = $this->prices->resolve($product, $distribution->reseller_id, $distribution->date);
                $distribution->fill([...$price->toArray(), 'override_reason' => null]);
            }

            $distribution->save();

            return $distribution->refresh();
        });
    }

    public function void(Distribution $distribution, string $reason, User $actor): Distribution
    {
        if ($distribution->isVoided()) {
            throw ValidationException::withMessages(['distribution' => 'This distribution is already voided.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to void.']);
        }

        $distribution->forceFill([
            'voided_at' => now(),
            'voided_by' => $actor->id,
            'void_reason' => $reason,
        ])->save();

        return $distribution;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function authorizeOverride(array $line, ?User $actor): void
    {
        if (empty($line['override_price'])) {
            return;
        }

        if ($actor && ! $actor->can('overridePrice', Distribution::class)) {
            throw new AuthorizationException('Only admins can override prices.');
        }

        if (! is_numeric($line['unit_price'] ?? null) || (float) $line['unit_price'] < 0) {
            throw ValidationException::withMessages(['unit_price' => 'Enter a valid price.']);
        }

        if (trim((string) ($line['override_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['override_reason' => 'A reason is required for a manual price.']);
        }
    }

    /**
     * Serialize concurrent stock-outs of the same product.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    private function lockProducts(array $ids): Collection
    {
        $products = Product::query()->whereKey(array_unique($ids))->lockForUpdate()->get()->keyBy('id');

        foreach (array_unique($ids) as $id) {
            if (! $products->has($id)) {
                throw ValidationException::withMessages(['product_id' => 'The selected product does not exist.']);
            }
        }

        return $products;
    }
}
