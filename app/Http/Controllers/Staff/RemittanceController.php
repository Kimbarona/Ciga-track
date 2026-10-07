<?php

namespace App\Http\Controllers\Staff;

use App\Enums\RemittanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RemittanceRequest;
use App\Models\Remittance;
use App\Services\RemittanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RemittanceController extends Controller
{
    public function __construct(
        private readonly RemittanceService $remittances,
        private readonly FormOptions $options,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Remittance::class);

        return Inertia::render('remittances/form', [
            'remittance' => null,
            'resellers' => $this->options->resellers(),
            'modes' => $this->options->modes(),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(RemittanceRequest $request): RedirectResponse
    {
        $remittance = $this->remittances->record($request->payload(), $request->user());

        $message = $remittance->status === RemittanceStatus::Pending
            ? 'Remittance saved — waiting for admin confirmation.'
            : 'Remittance saved and confirmed.';

        return to_route('remittances.create')->with('success', $message);
    }

    public function edit(Remittance $remittance): Response
    {
        $this->authorize('update', $remittance);

        return Inertia::render('remittances/form', [
            'remittance' => [
                'id' => $remittance->id,
                'date' => $remittance->date->toDateString(),
                'reseller_id' => $remittance->reseller_id,
                'amount' => (float) $remittance->amount,
                'mode' => $remittance->mode->value,
                'reference_no' => $remittance->reference_no,
                'notes' => $remittance->notes,
                'status' => $remittance->status->value,
                'proof_url' => $remittance->proof_path ? Storage::disk('public')->url($remittance->proof_path) : null,
            ],
            'resellers' => $this->options->resellers($remittance->reseller_id),
            'modes' => $this->options->modes(),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(RemittanceRequest $request, Remittance $remittance): RedirectResponse
    {
        $this->remittances->update($remittance, $request->payload());

        return to_route('my-entries', ['tab' => 'remittances'])->with('success', 'Remittance updated.');
    }
}
