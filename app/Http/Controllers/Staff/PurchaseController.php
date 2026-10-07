<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PurchaseRequest;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly FormOptions $options,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Purchase::class);

        return Inertia::render('purchases/form', [
            'purchase' => null,
            'products' => $this->options->products(),
            'suppliers' => $this->suppliers(),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $this->purchases->create($request->payload(), $request->user());

        return to_route('purchases.create')->with('success', 'Purchase saved — stock updated.');
    }

    public function edit(Request $request, Purchase $purchase): Response
    {
        $this->authorize('update', $purchase);

        $data = [
            'id' => $purchase->id,
            'date' => $purchase->date->toDateString(),
            'product_id' => $purchase->product_id,
            'qty' => $purchase->qty,
            'supplier' => $purchase->supplier,
            'notes' => $purchase->notes,
        ];

        // Cost is only sent back to admins — staff never receive cost data.
        if ($request->user()->can('view-financials')) {
            $data['unit_cost'] = (float) $purchase->unit_cost;
        }

        return Inertia::render('purchases/form', [
            'purchase' => $data,
            'products' => $this->options->products($purchase->product_id),
            'suppliers' => $this->suppliers(),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->purchases->update($purchase, $request->payload());

        return to_route('my-entries', ['tab' => 'purchases'])->with('success', 'Purchase updated.');
    }

    /**
     * @return list<string>
     */
    private function suppliers(): array
    {
        return Purchase::query()->whereNotNull('supplier')->distinct()->orderBy('supplier')->pluck('supplier')->all();
    }
}
