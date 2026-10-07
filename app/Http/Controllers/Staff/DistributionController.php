<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreDistributionRequest;
use App\Http\Requests\Staff\UpdateDistributionRequest;
use App\Models\Distribution;
use App\Services\DistributionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DistributionController extends Controller
{
    public function __construct(
        private readonly DistributionService $distributions,
        private readonly FormOptions $options,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Distribution::class);

        return Inertia::render('distributions/create', [
            'resellers' => $this->options->resellers(),
            'products' => $this->options->products(),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StoreDistributionRequest $request): RedirectResponse
    {
        $saved = $this->distributions->createMany(
            ['date' => $request->validated('date'), 'reseller_id' => $request->validated('reseller_id')],
            $request->lines(),
            $request->user(),
        );

        $reams = $saved->sum('qty');

        return to_route('distributions.create')->with('success', "Saved {$saved->count()} line(s) · {$reams} ream(s).");
    }

    public function edit(Distribution $distribution): Response
    {
        $this->authorize('update', $distribution);

        return Inertia::render('distributions/edit', [
            'distribution' => [
                'id' => $distribution->id,
                'date' => $distribution->date->toDateString(),
                'reseller_id' => $distribution->reseller_id,
                'product_id' => $distribution->product_id,
                'qty' => $distribution->qty,
                'unit_price' => (float) $distribution->unit_price,
                'price_source' => $distribution->price_source->value,
                'override_reason' => $distribution->override_reason,
                'notes' => $distribution->notes,
            ],
            'resellers' => $this->options->resellers($distribution->reseller_id),
            'products' => $this->options->products($distribution->product_id),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(UpdateDistributionRequest $request, Distribution $distribution): RedirectResponse
    {
        $this->distributions->update($distribution, $request->validated(), $request->user());

        return to_route('my-entries')->with('success', 'Distribution updated.');
    }
}
