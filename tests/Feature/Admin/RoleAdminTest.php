<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['role.viewAny', 'role.view', 'role.create', 'role.update', 'role.delete'] as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin');
});

it('creates a role with a permission set', function (): void {
    $admin = User::factory()->create()->givePermissionTo(['role.create', 'role.viewAny']);
    Permission::findOrCreate('grievance.viewAny');

    actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'reviewer',
            'permissions' => ['grievance.viewAny'],
        ])
        ->assertRedirect();

    $role = Role::findByName('reviewer');
    expect($role->hasPermissionTo('grievance.viewAny'))->toBeTrue();
});

it('protects the super-admin role from edit', function (): void {
    $admin = User::factory()->create()->givePermissionTo('role.update');
    $superAdmin = Role::findByName('super-admin');

    actingAs($admin)
        ->put(route('admin.roles.update', $superAdmin), [
            'name' => 'super-admin',
            'permissions' => [],
        ])
        ->assertForbidden();
});

it('protects the super-admin role from delete', function (): void {
    $admin = User::factory()->create()->givePermissionTo('role.delete');
    $superAdmin = Role::findByName('super-admin');

    actingAs($admin)
        ->delete(route('admin.roles.destroy', $superAdmin))
        ->assertForbidden();
});
