<?php

namespace App\Http\Controllers\Staff;

use App\Enums\RemittanceMode;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Reseller;
use App\Services\StockService;

/**
 * Select options shared by the staff entry forms. Contains no cost data.
 */
class FormOptions
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @return list<array{id: int, name: string, type: string, location: string|null}>
     */
    public function resellers(?int $includeId = null): array
    {
        return Reseller::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderByRaw("type = 'retail' desc")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'location'])
            ->map(fn (Reseller $r) => ['id' => $r->id, 'name' => $r->name, 'type' => $r->type->value, 'location' => $r->location])
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, name: string, stock: int}>
     */
    public function products(?int $includeId = null): array
    {
        $stock = $this->stock->stockByProduct();

        return Product::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'stock' => $stock[$p->id] ?? 0])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function modes(): array
    {
        return collect(RemittanceMode::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->getLabel()])->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function expenseCategories(): array
    {
        return ExpenseCategory::query()->orderBy('name')->get(['id', 'name'])->toArray();
    }
}
