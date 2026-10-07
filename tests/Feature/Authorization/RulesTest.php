<?php

use App\Enums\RemittanceStatus;
use App\Enums\Role;
use App\Models\Distribution;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\User;
use App\Services\RemittanceService;
use App\Services\UserManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedCatalog();
    stockUp('CR', 20);
    $this->users = app(UserManagementService::class);
});

describe('transactions', function () {
    test('staff cannot void, override prices or confirm remittances', function () {
        $staff = staff();
        $distribution = distribute('Tito Richie', 'CR', 1, '2026-09-20', $staff);
        $remittance = app(RemittanceService::class)->record(
            ['date' => '2026-09-20', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 100, 'mode' => 'cash'], $staff,
        );

        expect($staff->can('void', $distribution))->toBeFalse()
            ->and($staff->can('overridePrice', Distribution::class))->toBeFalse()
            ->and($staff->can('confirm', $remittance))->toBeFalse()
            ->and($staff->can('reject', $remittance))->toBeFalse();

        $admin = admin();
        expect($admin->can('void', $distribution))->toBeTrue()
            ->and($admin->can('overridePrice', Distribution::class))->toBeTrue()
            ->and($admin->can('confirm', $remittance))->toBeTrue();
    });

    test('a remittance encoded by staff is pending; by an admin it is confirmed', function () {
        $data = ['date' => '2026-09-20', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 100, 'mode' => 'cash'];

        $byStaff = app(RemittanceService::class)->record($data, staff());
        $byAdmin = app(RemittanceService::class)->record($data, admin());
        $bySuper = app(RemittanceService::class)->record($data, superAdmin());

        expect($byStaff->status)->toBe(RemittanceStatus::Pending)
            ->and($byStaff->confirmed_at)->toBeNull()
            ->and($byAdmin->status)->toBe(RemittanceStatus::Confirmed)
            ->and($byAdmin->confirmed_by)->toBe($byAdmin->recorded_by)
            ->and($bySuper->status)->toBe(RemittanceStatus::Confirmed);
    });

    test('staff can edit only their own entries on the same day', function () {
        $staff = staff();
        $other = staff();
        $mine = distribute('Tito Richie', 'CR', 1, '2026-09-20', $staff);
        $theirs = distribute('Tito Richie', 'CR', 1, '2026-09-20', $other);

        expect($staff->can('update', $mine))->toBeTrue()
            ->and($staff->can('update', $theirs))->toBeFalse();

        $this->travel(1)->days();
        expect($staff->can('update', $mine->fresh()))->toBeFalse()
            ->and(admin()->can('update', $mine->fresh()))->toBeTrue();
    });

    test('staff cannot edit a remittance once it is confirmed', function () {
        $staff = staff();
        $remittance = app(RemittanceService::class)->record(
            ['date' => '2026-09-20', 'reseller_id' => reseller('Tito Richie')->id, 'amount' => 100, 'mode' => 'cash'], $staff,
        );
        expect($staff->can('update', $remittance))->toBeTrue();

        app(RemittanceService::class)->confirm($remittance, admin());
        expect($staff->can('update', $remittance->fresh()))->toBeFalse();
    });

    test('nothing can be hard-deleted', function () {
        $distribution = distribute('Tito Richie', 'CR', 1, '2026-09-20');
        $remittance = Remittance::factory()->create();

        expect(superAdmin()->can('delete', $distribution))->toBeFalse()
            ->and(superAdmin()->can('delete', $remittance))->toBeFalse();
    });
});

describe('master data', function () {
    test('staff can view but not manage products, resellers or price rules', function () {
        $staff = staff();

        expect($staff->can('viewAny', Product::class))->toBeTrue()
            ->and($staff->can('create', Product::class))->toBeFalse()
            ->and($staff->can('update', reseller('Plaza')))->toBeFalse()
            ->and($staff->can('create', PriceRule::class))->toBeFalse();
    });

    test('only super admins can restore soft-deleted master data', function () {
        $plaza = reseller('Plaza');

        expect(admin()->can('restore', $plaza))->toBeFalse()
            ->and(superAdmin()->can('restore', $plaza))->toBeTrue();
    });

    test('financial gates are for admins only', function () {
        expect(staff()->can('view-financials'))->toBeFalse()
            ->and(staff()->can('view-reports'))->toBeFalse()
            ->and(admin()->can('view-financials'))->toBeTrue()
            ->and(admin()->can('manage-settings'))->toBeFalse()
            ->and(superAdmin()->can('manage-settings'))->toBeTrue();
    });
});

describe('user management', function () {
    test('admins can manage staff but not admins or super admins', function () {
        $admin = admin();
        $staff = staff();
        $super = superAdmin();

        expect($admin->can('update', $staff))->toBeTrue()
            ->and($admin->can('update', $super))->toBeFalse()
            ->and($admin->can('update', admin()))->toBeFalse()
            ->and($admin->can('changeRole', $staff))->toBeFalse();

        expect(fn () => $this->users->create(['name' => 'X', 'email' => 'x@example.com', 'role' => Role::SuperAdmin], $admin))
            ->toThrow(AuthorizationException::class);

        expect(fn () => $this->users->setActive($super, false, $admin))->toThrow(AuthorizationException::class);
        expect(fn () => $this->users->resetPassword($super, $admin))->toThrow(AuthorizationException::class);

        $created = $this->users->create(['name' => 'New Staff', 'email' => 'NEW@example.com', 'role' => Role::Staff], $admin);
        expect($created['user']->role)->toBe(Role::Staff)
            ->and($created['user']->email)->toBe('new@example.com')
            ->and($created['user']->must_change_password)->toBeTrue();
    });

    test('staff cannot manage users', function () {
        $staff = staff();

        expect($staff->can('viewAny', User::class))->toBeFalse()
            ->and($staff->can('update', staff()))->toBeFalse();
    });

    test('the last active super admin cannot be demoted or deactivated', function () {
        $only = superAdmin();
        $other = superAdmin();

        // With two super admins, one can be demoted…
        $this->users->changeRole($other, Role::Admin, $only);
        expect($other->fresh()->role)->toBe(Role::Admin);

        // …but now $only is the last one. Promote someone to try acting on $only.
        $second = superAdmin(['is_active' => false]); // inactive super admins do not count

        expect(fn () => $this->users->changeRole($only, Role::Admin, $second))
            ->toThrow(ValidationException::class, 'at least one active super admin');
        expect(fn () => $this->users->setActive($only, false, $second))
            ->toThrow(ValidationException::class, 'at least one active super admin');

        expect($only->fresh()->role)->toBe(Role::SuperAdmin)
            ->and($only->fresh()->is_active)->toBeTrue();
    });

    test('users cannot change their own role or deactivate themselves', function () {
        $super = superAdmin();
        superAdmin(); // so the "last super admin" rule is not what blocks it

        expect(fn () => $this->users->changeRole($super, Role::Staff, $super))
            ->toThrow(ValidationException::class, 'own role');
        expect(fn () => $this->users->setActive($super, false, $super))
            ->toThrow(ValidationException::class, 'deactivate your own');
    });

    test('a reset password must be changed on next login', function () {
        $staff = staff();

        $password = $this->users->resetPassword($staff, admin());

        expect($staff->fresh()->must_change_password)->toBeTrue()
            ->and(Hash::check($password, $staff->fresh()->password))->toBeTrue();
    });
});
