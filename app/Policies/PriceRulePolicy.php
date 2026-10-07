<?php

namespace App\Policies;

use App\Models\PriceRule;
use App\Models\User;

/**
 * Price changes are new rows. A rule used by any distribution is locked.
 */
class PriceRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PriceRule $rule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PriceRule $rule): bool
    {
        return $user->isAdmin() && ! $rule->isLocked();
    }

    public function delete(User $user, PriceRule $rule): bool
    {
        return $user->isAdmin() && ! $rule->isLocked();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reprice(User $user, PriceRule $rule): bool
    {
        return $user->isAdmin();
    }
}
