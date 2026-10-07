<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Account management with the safety rules:
 *  - super admins manage everyone; admins manage staff only
 *  - nobody changes their own role or deactivates themselves
 *  - there is always at least one active super admin
 *  - accounts created / reset by someone else must change their password on first login
 */
class UserManagementService
{
    /**
     * @param  array{name: string, email: string, role: Role|string, password?: string|null, is_active?: bool}  $data
     * @return array{user: User, password: string}
     */
    public function create(array $data, User $actor): array
    {
        $role = Role::from($data['role'] instanceof Role ? $data['role']->value : $data['role']);
        $this->assertCanAssignRole($actor, $role);

        $password = filled($data['password'] ?? null) ? $data['password'] : $this->temporaryPassword();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'role' => $role,
            'is_active' => $data['is_active'] ?? true,
            'password' => $password,
            'must_change_password' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return ['user' => $user, 'password' => $password];
    }

    /**
     * @param  array{name?: string, email?: string, role?: Role|string, is_active?: bool}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $this->assertCanManage($actor, $user);

        return DB::transaction(function () use ($user, $data, $actor) {
            if (array_key_exists('role', $data) && $data['role'] !== null) {
                $role = $data['role'] instanceof Role ? $data['role'] : Role::from($data['role']);

                if ($role !== $user->role) {
                    $this->changeRole($user, $role, $actor);
                }
            }

            if (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== $user->is_active) {
                $this->setActive($user, (bool) $data['is_active'], $actor);
            }

            $user->fill(Arr::only($data, ['name', 'email']))->save();

            return $user;
        });
    }

    public function changeRole(User $user, Role $role, User $actor): User
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a super admin can change roles.');
        }

        if ($user->role === Role::SuperAdmin && $role !== Role::SuperAdmin) {
            $this->assertNotLastSuperAdmin($user, 'role');
        }

        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        $this->assertCanManage($actor, $user);

        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
        }

        if (! $active && $user->role === Role::SuperAdmin) {
            $this->assertNotLastSuperAdmin($user, 'is_active');
        }

        $user->forceFill(['is_active' => $active])->save();

        return $user;
    }

    /**
     * Sets a new (temporary) password and forces a change on next login.
     */
    public function resetPassword(User $user, User $actor, ?string $password = null): string
    {
        $this->assertCanManage($actor, $user);

        $password = filled($password) ? $password : $this->temporaryPassword();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => ! $actor->is($user),
        ])->save();

        activity('users')->performedOn($user)->causedBy($actor)->event('password_reset')->log('Password reset');

        return $password;
    }

    public function canManage(User $actor, User $target): bool
    {
        return match (true) {
            $actor->isSuperAdmin() => true,
            $actor->isAdmin() => $target->role === Role::Staff,
            default => false,
        };
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw new AuthorizationException('You are not allowed to manage this user.');
        }
    }

    private function assertCanAssignRole(User $actor, Role $role): void
    {
        $allowed = $actor->isSuperAdmin() || ($actor->isAdmin() && $role === Role::Staff);

        if (! $allowed) {
            throw new AuthorizationException('You are not allowed to create users with this role.');
        }
    }

    private function assertNotLastSuperAdmin(User $user, string $field): void
    {
        $others = User::query()
            ->where('role', Role::SuperAdmin)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                $field => 'There must always be at least one active super admin.',
            ]);
        }
    }

    private function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }
}
