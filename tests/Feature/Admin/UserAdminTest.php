<?php

declare(strict_types=1);

use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['user.viewAny', 'user.view', 'user.create', 'user.update', 'user.assign_roles'] as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin');
    Role::findOrCreate('grm-officer');
});

it('blocks users without user.viewAny', function (): void {
    actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('lists users for authorized admins', function (): void {
    $user = User::factory()->create()->givePermissionTo('user.viewAny');
    User::factory()->count(2)->create();

    actingAs($user)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
});

it('assigns roles and dispatches the UserRoleAssigned event', function (): void {
    Event::fake([UserRoleAssigned::class]);

    $admin = User::factory()->create();
    $admin->givePermissionTo(['user.view', 'user.update', 'user.assign_roles']);
    $target = User::factory()->create();

    actingAs($admin)
        ->put(route('admin.users.roles.update', $target), ['roles' => ['grm-officer']])
        ->assertRedirect();

    expect($target->fresh()->hasRole('grm-officer'))->toBeTrue();
    Event::assertDispatched(UserRoleAssigned::class);
});

it('forbids non-super-admin from granting super-admin role', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['user.view', 'user.update', 'user.assign_roles']);
    $target = User::factory()->create();

    actingAs($admin)
        ->put(route('admin.users.roles.update', $target), ['roles' => ['super-admin']])
        ->assertForbidden();

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('forbids non-super-admin from stripping super-admin from someone', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['user.view', 'user.update', 'user.assign_roles']);
    $target = User::factory()->create();
    $target->assignRole('super-admin');

    actingAs($admin)
        ->put(route('admin.users.roles.update', $target), ['roles' => []])
        ->assertForbidden();
});
