<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for encoded transactions:
 *  - everyone can encode
 *  - admins can edit anything that is not voided, and void with a reason
 *  - staff can edit only their own entries, on the day they encoded them
 *  - nothing is ever hard-deleted
 */
trait AuthorizesTransactions
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $model);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        if ($model->isVoided()) {
            return false;
        }

        return $user->isAdmin() || ($this->isOwner($user, $model) && $model->created_at?->isToday());
    }

    public function void(User $user, Model $model): bool
    {
        return $user->isAdmin() && ! $model->isVoided();
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    protected function isOwner(User $user, Model $model): bool
    {
        return (int) $model->created_by === $user->id;
    }
}
