<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array{date: string, product_id: int, qty: int, unit_cost: mixed, supplier?: string|null, notes?: string|null}  $data
     */
    public function create(array $data, ?User $actor): Purchase
    {
        return Purchase::query()->create([
            ...Arr::only($data, ['date', 'product_id', 'qty', 'unit_cost', 'supplier', 'notes']),
            'created_by' => $actor?->id,
        ]);
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        if ($purchase->isVoided()) {
            throw ValidationException::withMessages(['purchase' => 'A voided purchase cannot be edited.']);
        }

        return DB::transaction(function () use ($purchase, $data) {
            $originalProduct = $purchase->product_id;
            $originalQty = $purchase->qty;

            $purchase->fill(Arr::only($data, ['date', 'product_id', 'qty', 'unit_cost', 'supplier', 'notes']));

            // Reducing (or moving) stock-in must not leave already-distributed stock negative.
            $removedFromOriginal = $originalProduct === $purchase->product_id
                ? $originalQty - $purchase->qty
                : $originalQty;

            if ($removedFromOriginal > 0) {
                $product = Product::query()->lockForUpdate()->findOrFail($originalProduct);
                $this->stock->assertCanRemove($product, $removedFromOriginal);
            }

            $purchase->save();

            return $purchase;
        });
    }

    public function void(Purchase $purchase, string $reason, User $actor): Purchase
    {
        if ($purchase->isVoided()) {
            throw ValidationException::withMessages(['purchase' => 'This purchase is already voided.']);
        }

        return DB::transaction(function () use ($purchase, $reason, $actor) {
            $product = Product::query()->lockForUpdate()->findOrFail($purchase->product_id);
            $this->stock->assertCanRemove($product, $purchase->qty, field: 'reason');

            $purchase->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();

            return $purchase;
        });
    }
}
