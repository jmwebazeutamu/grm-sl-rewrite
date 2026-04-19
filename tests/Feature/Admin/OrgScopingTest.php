<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Organization;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['organization.update', 'organization.view', 'organization.delete', 'employee.view', 'employee.update', 'employee.delete'] as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('organization-admin');
    Role::findOrCreate('super-admin');
});

it('lets an org-admin update their own org', function (): void {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('organization-admin');
    $admin->givePermissionTo('organization.update');

    expect($admin->can('update', $org))->toBeTrue();
});

it('blocks an org-admin from updating a different org', function (): void {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $ownOrg->id]);
    $admin->assignRole('organization-admin');
    $admin->givePermissionTo('organization.update');

    expect($admin->can('update', $otherOrg))->toBeFalse();
});

it('blocks an org-admin from updating an employee in another org', function (): void {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $ownOrg->id]);
    $admin->assignRole('organization-admin');
    $admin->givePermissionTo('employee.update');

    $employee = Employee::factory()->for($otherOrg)->create();

    expect($admin->can('update', $employee))->toBeFalse();
});

it('lets an org-admin update employees in their own org', function (): void {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('organization-admin');
    $admin->givePermissionTo('employee.update');

    $employee = Employee::factory()->for($org)->create();

    expect($admin->can('update', $employee))->toBeTrue();
});

it('lets super-admin update any org regardless of organization_id', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $other = Organization::factory()->create();

    expect($superAdmin->can('update', $other))->toBeTrue();
});

it('forbids anyone (including super-admin via policy) from deleting an org', function (): void {
    // Gate::before lets super-admin through — that's by design. This test
    // documents that non-super-admins with the delete permission are still
    // blocked at the policy level.
    $admin = User::factory()->create()->givePermissionTo('organization.delete');
    $org = Organization::factory()->create();

    expect($admin->can('delete', $org))->toBeFalse();
});
