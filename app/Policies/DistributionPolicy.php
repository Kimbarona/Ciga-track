<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTransactions;

class DistributionPolicy
{
    use AuthorizesTransactions;

    public function overridePrice(User $user): bool
    {
        return $user->isAdmin();
    }
}
