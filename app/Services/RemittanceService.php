<?php

namespace App\Services;

use App\Enums\RemittanceStatus;
use App\Models\Remittance;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Remittances encoded by staff start as PENDING; admins' entries are CONFIRMED immediately.
 * The status is always decided here from the actor's role — never taken from the request.
 */
class RemittanceService
{
    private const FIELDS = ['date', 'reseller_id', 'amount', 'mode', 'reference_no', 'proof_path', 'notes'];

    public function record(array $data, ?User $actor, ?RemittanceStatus $forceStatus = null): Remittance
    {
        $status = $forceStatus ?? ($actor?->isAdmin() ? RemittanceStatus::Confirmed : RemittanceStatus::Pending);

        $remittance = new Remittance(Arr::only($data, self::FIELDS));
        $remittance->forceFill([
            'status' => $status,
            'recorded_by' => $actor?->id,
            'confirmed_by' => $status === RemittanceStatus::Confirmed ? $actor?->id : null,
            'confirmed_at' => $status === RemittanceStatus::Confirmed ? now() : null,
        ])->save();

        return $remittance;
    }

    public function update(Remittance $remittance, array $data): Remittance
    {
        if ($remittance->status === RemittanceStatus::Rejected) {
            throw ValidationException::withMessages(['remittance' => 'A rejected remittance cannot be edited.']);
        }

        $remittance->update(Arr::only($data, self::FIELDS));

        return $remittance;
    }

    public function confirm(Remittance $remittance, User $actor): Remittance
    {
        $this->assertPending($remittance);

        $remittance->forceFill([
            'status' => RemittanceStatus::Confirmed,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return $remittance;
    }

    public function reject(Remittance $remittance, string $reason, User $actor): Remittance
    {
        $this->assertPending($remittance);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'A reason is required to reject.']);
        }

        $remittance->forceFill([
            'status' => RemittanceStatus::Rejected,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $remittance;
    }

    private function assertPending(Remittance $remittance): void
    {
        if (! $remittance->isPending()) {
            throw ValidationException::withMessages(['remittance' => 'Only pending remittances can be confirmed or rejected.']);
        }
    }
}
