<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BalanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Balances\BalanceService;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    public function __construct(private readonly BalanceService $balances) {}

    public function index(): Response
    {
        return Inertia::render('resellers/index', [
            'resellers' => Inertia::defer(fn () => $this->balances
                ->withTotals(Reseller::query()->where('is_active', true))
                ->orderByDesc('balance')
                ->orderBy('name')
                ->get()
                ->map(fn (Reseller $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'type' => $r->type->value,
                    'location' => $r->location,
                    ...$this->balances->fromRow($r)->toArray(),
                ])
                ->all()),
        ]);
    }

    public function show(Reseller $reseller): Response
    {
        $this->authorize('view', $reseller);

        return Inertia::render('resellers/show', [
            'reseller' => [
                'id' => $reseller->id,
                'name' => $reseller->name,
                'type' => $reseller->type->value,
                'location' => $reseller->location,
                'contact' => $reseller->contact,
            ],
            'balance' => $this->balances->forReseller($reseller)->toArray(),
            'ledger' => Inertia::defer(function () use ($reseller) {
                $ledger = $this->balances->ledger($reseller, includeUnconfirmed: true);

                return [
                    'opening' => $ledger['opening'],
                    'closing' => $ledger['closing'],
                    'status' => BalanceStatus::fromBalance($ledger['closing'])->value,
                    // Newest first for the timeline.
                    'entries' => $ledger['entries']->reverse()->values()->map(fn (array $e) => [
                        ...$e,
                        'date' => $e['date']->toDateString(),
                        'date_label' => $e['date']->format('M d, Y'),
                    ])->all(),
                ];
            }),
        ]);
    }
}
