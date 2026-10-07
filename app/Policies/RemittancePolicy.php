<?php

namespace App\Policies;

use App\Enums\RemittanceStatus;
use App\Models\Remittance;
use App\Models\User;

/**
 * Remittances are never voided or deleted; wrong entries are rejected.
 */
class RemittancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Remittance $remittance): bool
    {
        return $user->isAdmin() || (int) $remittance->recorded_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Remittance $remittance): bool
    {
        if ($remittance->status === RemittanceStatus::Rejected) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $remittance->recorded_by === $user->id
            && $remittance->isPending()
            && $remittance->created_at?->isToday();
    }

    public function confirm(User $user, Remittance $remittance): bool
    {
        return $user->isAdmin() && $remittance->isPending();
    }

    public function reject(User $user, Remittance $remittance): bool
    {
        return $this->confirm($user, $remittance);
    }

    public function delete(User $user, Remittance $remittance): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
