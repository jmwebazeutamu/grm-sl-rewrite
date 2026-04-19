<?php

declare(strict_types=1);

use App\Domain\Identity\Events\UserDeactivated;
use App\Domain\Identity\Events\UserReactivated;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function setupUserMgmtPerms(): void
{
    $perms = [
        'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.assign_roles',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('acc-reviewer');
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer');
    Role::findOrCreate('complainant');
}

function orgAdminIn(Organization $org): User
{
    $u = User::factory()->create(['organization_id' => $org->id]);
    $u->assignRole('org-admin');

    return $u;
}

function memberIn(Organization $org, string $role = 'organization-officer'): User
{
    $u = User::factory()->create(['organization_id' => $org->id]);
    $u->assignRole($role);

    return $u;
}

beforeEach(function (): void {
    setupUserMgmtPerms();
});

//
// 1. Visibility — index scoped to own org
//

it('lets org-admin view users in their own org', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM01']);
    $admin = orgAdminIn($org);
    $peer = memberIn($org);

    actingAs($admin)
        ->get(route('admin.org.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/Users/Index')
            ->where('users.data', fn ($rows) => collect($rows)->pluck('id')->contains($peer->id))
        );
});

it('does not expose users from another org in the list', function (): void {
    $own = Organization::factory()->create(['acronym' => 'UM02']);
    $other = Organization::factory()->create(['acronym' => 'UM02B']);
    $admin = orgAdminIn($own);
    $outsider = memberIn($other);

    actingAs($admin)
        ->get(route('admin.org.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($rows) => ! collect($rows)->pluck('id')->contains($outsider->id))
        );
});

//
// 2. Edit details
//

it('lets org-admin edit details of a user in their org', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM03']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);

    actingAs($admin)
        ->put(route('admin.org.users.update', $target), [
            'name' => 'Updated Name',
            'email' => 'updated@example.sl',
            'phone_number' => '+2327611111',
            'position' => 'Senior Officer',
        ])
        ->assertRedirect(route('admin.org.users.show', $target));

    $target->refresh();
    expect($target->name)->toBe('Updated Name');
    expect($target->email)->toBe('updated@example.sl');
    expect($target->phone_number)->toBe('+2327611111');
    expect($target->position)->toBe('Senior Officer');
});

it('does not allow org-admin to edit username or organization via details form', function (): void {
    $own = Organization::factory()->create(['acronym' => 'UM04']);
    $other = Organization::factory()->create(['acronym' => 'UM04B']);
    $admin = orgAdminIn($own);
    $target = memberIn($own);
    $originalUsername = $target->username;

    actingAs($admin)
        ->put(route('admin.org.users.update', $target), [
            'name' => 'New Name',
            'username' => 'smuggled_username',
            'organization_id' => $other->id,
        ])
        ->assertRedirect(route('admin.org.users.show', $target));

    $target->refresh();
    expect($target->username)->toBe($originalUsername);
    expect($target->organization_id)->toBe($own->id);
});

it('blocks org-admin from editing a user in another org', function (): void {
    $own = Organization::factory()->create(['acronym' => 'UM05']);
    $other = Organization::factory()->create(['acronym' => 'UM05B']);
    $admin = orgAdminIn($own);
    $outsider = memberIn($other);

    actingAs($admin)
        ->put(route('admin.org.users.update', $outsider), [
            'name' => 'Should Not Save',
        ])
        ->assertForbidden();

    expect($outsider->fresh()->name)->not->toBe('Should Not Save');
});

//
// 3. Reset password — by email
//

it('lets org-admin send a password-reset email', function (): void {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn(Password::RESET_LINK_SENT);

    $org = Organization::factory()->create(['acronym' => 'UM06']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);

    actingAs($admin)
        ->post(route('admin.org.users.password.email', $target))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('fails gracefully when resetting password by email for a user with no email', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM07']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);
    $target->email = null;
    $target->save();

    actingAs($admin)
        ->post(route('admin.org.users.password.email', $target))
        ->assertRedirect()
        ->assertSessionHas('error');
});

//
// 4. Reset password — direct
//

it('lets org-admin set a password directly', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM08']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);

    actingAs($admin)
        ->post(route('admin.org.users.password.set', $target), [
            'password' => 'BrandNewPass123!',
            'password_confirmation' => 'BrandNewPass123!',
        ])
        ->assertRedirect(route('admin.org.users.show', $target));

    expect(Hash::check('BrandNewPass123!', $target->fresh()->password))->toBeTrue();
});

it('stores direct-set password as bcrypt hash, not plain text', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM09']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);

    actingAs($admin)
        ->post(route('admin.org.users.password.set', $target), [
            'password' => 'PlainTextPassword9',
            'password_confirmation' => 'PlainTextPassword9',
        ])
        ->assertRedirect();

    $hashed = $target->fresh()->password;
    expect($hashed)->not->toBe('PlainTextPassword9');
    expect(Hash::check('PlainTextPassword9', $hashed))->toBeTrue();
});

it('blocks org-admin from resetting password of super-admin or acc-reviewer', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM10']);
    $admin = orgAdminIn($org);

    $superAdmin = User::factory()->create(['organization_id' => $org->id]);
    $superAdmin->assignRole('super-admin');

    $accReviewer = User::factory()->create(['organization_id' => $org->id]);
    $accReviewer->assignRole('acc-reviewer');

    actingAs($admin)
        ->post(route('admin.org.users.password.set', $superAdmin), [
            'password' => 'ShouldFail123!',
            'password_confirmation' => 'ShouldFail123!',
        ])
        ->assertForbidden();

    actingAs($admin)
        ->post(route('admin.org.users.password.set', $accReviewer), [
            'password' => 'ShouldFail123!',
            'password_confirmation' => 'ShouldFail123!',
        ])
        ->assertForbidden();
});

//
// 5. Deactivate / reactivate
//

it('lets org-admin deactivate a user in their org', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM11']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);

    actingAs($admin)
        ->post(route('admin.org.users.deactivate', $target))
        ->assertRedirect(route('admin.org.users.index'));

    expect($target->fresh()->is_active)->toBeFalse();
});

it('redirects a deactivated user to login with a message', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM12']);
    $admin = orgAdminIn($org);

    // Deactivate and try to hit a protected page.
    $admin->update(['is_active' => false]);

    actingAs($admin)
        ->get(route('admin.org.users.index'))
        ->assertRedirect(route('login'));
});

it('lets org-admin reactivate a deactivated user', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM13']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);
    $target->update(['is_active' => false]);

    actingAs($admin)
        ->post(route('admin.org.users.reactivate', $target))
        ->assertRedirect(route('admin.org.users.index'));

    expect($target->fresh()->is_active)->toBeTrue();
});

it('blocks org-admin from deactivating themselves', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM14']);
    $admin = orgAdminIn($org);

    actingAs($admin)
        ->post(route('admin.org.users.deactivate', $admin))
        ->assertForbidden();

    expect($admin->fresh()->is_active)->not->toBeFalse();
});

it('blocks org-admin from deactivating a super-admin', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM15']);
    $admin = orgAdminIn($org);
    $superAdmin = User::factory()->create(['organization_id' => $org->id]);
    $superAdmin->assignRole('super-admin');

    actingAs($admin)
        ->post(route('admin.org.users.deactivate', $superAdmin))
        ->assertForbidden();

    expect($superAdmin->fresh()->is_active)->not->toBeFalse();
});

it('grm-officer can edit details but cannot deactivate users', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM16']);
    $grm = memberIn($org, 'grm-officer');
    $target = memberIn($org);

    actingAs($grm)
        ->put(route('admin.org.users.update', $target), [
            'name' => 'GRM Edited Name',
        ])
        ->assertRedirect();
    expect($target->fresh()->name)->toBe('GRM Edited Name');

    actingAs($grm)
        ->post(route('admin.org.users.deactivate', $target))
        ->assertForbidden();
    expect($target->fresh()->is_active)->not->toBeFalse();
});

it('denies organization-officer access to all user management routes', function (): void {
    $org = Organization::factory()->create(['acronym' => 'UM17']);
    $officer = memberIn($org, 'organization-officer');
    $target = memberIn($org);

    actingAs($officer)->get(route('admin.org.users.edit', $target))->assertForbidden();
    actingAs($officer)->put(route('admin.org.users.update', $target), ['name' => 'x'])->assertForbidden();
    actingAs($officer)->get(route('admin.org.users.password', $target))->assertForbidden();
    actingAs($officer)->post(route('admin.org.users.deactivate', $target))->assertForbidden();
});

//
// 6. Events fire
//

it('dispatches UserDeactivated when deactivating a user', function (): void {
    Event::fake([UserDeactivated::class]);

    $org = Organization::factory()->create(['acronym' => 'UM18']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);

    actingAs($admin)->post(route('admin.org.users.deactivate', $target));

    Event::assertDispatched(UserDeactivated::class, fn ($event) => $event->user->id === $target->id);
});

it('dispatches UserReactivated when reactivating a user', function (): void {
    Event::fake([UserReactivated::class]);

    $org = Organization::factory()->create(['acronym' => 'UM19']);
    $admin = orgAdminIn($org);
    $target = memberIn($org);
    $target->update(['is_active' => false]);

    actingAs($admin)->post(route('admin.org.users.reactivate', $target));

    Event::assertDispatched(UserReactivated::class, fn ($event) => $event->user->id === $target->id);
});
