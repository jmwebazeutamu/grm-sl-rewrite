<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupOrgAdminTestPerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.create', 'grievance.update',
        'grievance.transition', 'grievance.assign', 'grievance.review',
        'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.assign_roles',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('acc-reviewer')->givePermissionTo(array_filter($perms, fn ($p) => str_starts_with($p, 'grievance.')));
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo(array_filter($perms, fn ($p) => str_starts_with($p, 'grievance.') || in_array($p, ['user.viewAny', 'user.view', 'user.create', 'user.update', 'user.assign_roles'], true)));
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view', 'grievance.update', 'grievance.transition']);
    Role::findOrCreate('complainant');
}

function ensureAccOrg(): Organization
{
    return Organization::firstOrCreate(
        ['acronym' => 'ACC'],
        ['name' => 'Anti-Corruption Commission'],
    );
}

beforeEach(function (): void {
    setupOrgAdminTestPerms();
    Mail::fake();
    Notification::fake();
    Password::shouldReceive('sendResetLink')->andReturn(Password::RESET_LINK_SENT);
});

//
// PATH 1 — Invite by email (POST /admin/org/users/invite)
//

it('lets an org-admin invite a user in their own org', function (): void {
    $org = Organization::factory()->create(['name' => 'MoH Test', 'acronym' => 'MOHT']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.invite'), [
        'username' => 'new_user',
        'name' => 'New User',
        'email' => 'newuser@example.sl',
        'role' => 'organization-officer',
    ])->assertRedirect(route('admin.org.users.index'));

    $invited = User::where('username', 'new_user')->firstOrFail();
    expect($invited->organization_id)->toBe($org->id);
    expect($invited->hasRole('organization-officer'))->toBeTrue();
});

it('invite forces organization_id to the admin\'s own org (no cross-org smuggling)', function (): void {
    $org = Organization::factory()->create(['acronym' => 'ORG1']);
    $otherOrg = Organization::factory()->create(['acronym' => 'ORG2']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.invite'), [
        'username' => 'boundary',
        'name' => 'Boundary User',
        'email' => 'boundary@example.sl',
        'role' => 'organization-officer',
        'organization_id' => $otherOrg->id, // attempted smuggle
    ])->assertRedirect();

    expect(User::where('username', 'boundary')->first()->organization_id)->toBe($org->id);
});

it('blocks invite from assigning super-admin, acc-reviewer, or org-admin', function (): void {
    $org = Organization::factory()->create(['acronym' => 'OBLK']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    foreach (['super-admin', 'acc-reviewer', 'org-admin'] as $role) {
        actingAs($admin)->post(route('admin.org.users.invite'), [
            'username' => "attempt_{$role}",
            'name' => "Attempt {$role}",
            'email' => "{$role}@example.sl",
            'role' => $role,
        ]);
        expect(User::where('username', "attempt_{$role}")->exists())->toBeFalse();
    }
});

//
// PATH 2 — Direct create with password (POST /admin/org/users)
//

it('lets an org-admin directly create a user with a password', function (): void {
    Event::fake([UserRoleAssigned::class]);

    $org = Organization::factory()->create(['acronym' => 'DCOH']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'direct_user',
        'name' => 'Direct User',
        'phone_number' => '+2327600000',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => 'organization-officer',
    ])->assertRedirect(route('admin.org.users.index'));

    $created = User::where('username', 'direct_user')->firstOrFail();
    expect($created->organization_id)->toBe($org->id);
    expect($created->hasRole('organization-officer'))->toBeTrue();
    expect(Hash::check('correct-horse-battery-staple', $created->password))->toBeTrue();
    expect($created->email_verified_at)->not->toBeNull();

    Event::assertDispatched(UserRoleAssigned::class);
});

it('direct create does NOT send a password-reset email', function (): void {
    // Intentionally don't stub Password::sendResetLink here; the test relies
    // on the outer beforeEach. If the controller hit it by mistake we'd see
    // the shouldReceive succeed but the count check below would be wrong.
    $org = Organization::factory()->create(['acronym' => 'NOML']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    Password::shouldReceive('sendResetLink')
        ->times(0)
        ->andReturn(Password::RESET_LINK_SENT);

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'no_mail',
        'name' => 'No Mail',
        'password' => 'StrongPassw0rd!',
        'password_confirmation' => 'StrongPassw0rd!',
        'role' => 'organization-officer',
    ])->assertRedirect();

    Mail::assertNothingSent();
});

it('direct create accepts a user with no email address', function (): void {
    $org = Organization::factory()->create(['acronym' => 'NOEM']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'no_email_user',
        'name' => 'No Email User',
        'password' => 'StrongPassw0rd!',
        'password_confirmation' => 'StrongPassw0rd!',
        'role' => 'organization-officer',
    ])->assertRedirect();

    $u = User::where('username', 'no_email_user')->firstOrFail();
    expect($u->email)->toBeNull();
});

it('direct create rejects a short password', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SHRT']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'short_pw',
        'name' => 'Short PW',
        'password' => 'abc',
        'password_confirmation' => 'abc',
        'role' => 'organization-officer',
    ])->assertSessionHasErrors('password');

    expect(User::where('username', 'short_pw')->exists())->toBeFalse();
});

it('direct create rejects mismatched confirmation', function (): void {
    $org = Organization::factory()->create(['acronym' => 'MIS']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'mismatch_pw',
        'name' => 'Mismatch',
        'password' => 'StrongPassw0rd!',
        'password_confirmation' => 'DifferentPassw0rd!',
        'role' => 'organization-officer',
    ])->assertSessionHasErrors('password');
});

it('direct create forces organization_id to the admin\'s own org', function (): void {
    $org = Organization::factory()->create(['acronym' => 'OW1']);
    $otherOrg = Organization::factory()->create(['acronym' => 'OW2']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'direct_boundary',
        'name' => 'Direct Boundary',
        'password' => 'StrongPassw0rd!',
        'password_confirmation' => 'StrongPassw0rd!',
        'role' => 'organization-officer',
        'organization_id' => $otherOrg->id, // attempted smuggle
    ])->assertRedirect();

    expect(User::where('username', 'direct_boundary')->first()->organization_id)->toBe($org->id);
});

it('direct create blocks restricted roles the same as invite', function (): void {
    $org = Organization::factory()->create(['acronym' => 'DCRR']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    foreach (['super-admin', 'acc-reviewer', 'org-admin'] as $role) {
        actingAs($admin)->post(route('admin.org.users.store'), [
            'username' => "direct_{$role}",
            'name' => "Direct {$role}",
            'password' => 'StrongPassw0rd!',
            'password_confirmation' => 'StrongPassw0rd!',
            'role' => $role,
        ]);
        expect(User::where('username', "direct_{$role}")->exists())->toBeFalse();
    }
});

it('direct create stores password hashed, not in plain text', function (): void {
    $org = Organization::factory()->create(['acronym' => 'DCHS']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)->post(route('admin.org.users.store'), [
        'username' => 'hashed_user',
        'name' => 'Hashed User',
        'password' => 'PlainValue123',
        'password_confirmation' => 'PlainValue123',
        'role' => 'organization-officer',
    ])->assertRedirect();

    $u = User::where('username', 'hashed_user')->firstOrFail();
    expect($u->password)->not->toBe('PlainValue123');
    expect(Hash::check('PlainValue123', $u->password))->toBeTrue();
});

//
// SHARED — grm-officer / organization-officer behaviour
//

it('grm-officer can still invite users (backwards compatible)', function (): void {
    $org = Organization::factory()->create(['acronym' => 'OGRM']);
    $grm = User::factory()->create(['organization_id' => $org->id]);
    $grm->assignRole('grm-officer');

    actingAs($grm)->get(route('admin.org.users.index'))->assertOk();

    actingAs($grm)->post(route('admin.org.users.invite'), [
        'username' => 'grm_invited',
        'name' => 'GRM Invited',
        'email' => 'grminv@example.sl',
        'role' => 'organization-officer',
    ])->assertRedirect();

    expect(User::where('username', 'grm_invited')->first()->organization_id)->toBe($org->id);
});

it('grm-officer can also directly create users', function (): void {
    $org = Organization::factory()->create(['acronym' => 'OGRD']);
    $grm = User::factory()->create(['organization_id' => $org->id]);
    $grm->assignRole('grm-officer');

    actingAs($grm)->post(route('admin.org.users.store'), [
        'username' => 'grm_direct',
        'name' => 'GRM Direct',
        'password' => 'StrongPassw0rd!',
        'password_confirmation' => 'StrongPassw0rd!',
        'role' => 'organization-officer',
    ])->assertRedirect();

    $u = User::where('username', 'grm_direct')->firstOrFail();
    expect($u->organization_id)->toBe($org->id);
    expect(Hash::check('StrongPassw0rd!', $u->password))->toBeTrue();
});

it('grm-officer cannot assign restricted roles on either path', function (): void {
    $org = Organization::factory()->create(['acronym' => 'OGRR']);
    $grm = User::factory()->create(['organization_id' => $org->id]);
    $grm->assignRole('grm-officer');

    foreach (['super-admin', 'acc-reviewer', 'org-admin'] as $role) {
        actingAs($grm)->post(route('admin.org.users.invite'), [
            'username' => "grm_inv_{$role}",
            'name' => "GRM invite {$role}",
            'email' => "grm_inv_{$role}@example.sl",
            'role' => $role,
        ]);
        expect(User::where('username', "grm_inv_{$role}")->exists())->toBeFalse();

        actingAs($grm)->post(route('admin.org.users.store'), [
            'username' => "grm_dir_{$role}",
            'name' => "GRM direct {$role}",
            'password' => 'StrongPassw0rd!',
            'password_confirmation' => 'StrongPassw0rd!',
            'role' => $role,
        ]);
        expect(User::where('username', "grm_dir_{$role}")->exists())->toBeFalse();
    }
});

it('denies organization-officer access to the user management surface', function (): void {
    $org = Organization::factory()->create(['acronym' => 'OOF']);
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('organization-officer');

    actingAs($officer)->get(route('admin.org.users.index'))->assertForbidden();
    actingAs($officer)->get(route('admin.org.users.create'))->assertForbidden();
    actingAs($officer)->post(route('admin.org.users.invite'), [
        'username' => 'x', 'name' => 'X', 'email' => 'x@example.sl', 'role' => 'organization-officer',
    ])->assertForbidden();
    actingAs($officer)->post(route('admin.org.users.store'), [
        'username' => 'y', 'name' => 'Y',
        'password' => 'StrongPassw0rd!', 'password_confirmation' => 'StrongPassw0rd!',
        'role' => 'organization-officer',
    ])->assertForbidden();
});

//
// Case-visibility tests (unchanged, included for regression)
//

it('lets an org-admin view cases classified to their org', function (): void {
    ensureAccOrg();
    $org = Organization::factory()->create(['acronym' => 'OVIEW']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create(['name' => 'Service Delivery']);
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);

    actingAs($admin)->get(route('admin.grievances.show', $case))->assertOk();
});

it('blocks an org-admin from viewing a case classified to a different org', function (): void {
    ensureAccOrg();
    $own = Organization::factory()->create(['acronym' => 'OOWN']);
    $other = Organization::factory()->create(['acronym' => 'OOTH']);
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create(['name' => 'Service Delivery']);
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $other->id,
    ]);

    actingAs($admin)->get(route('admin.grievances.show', $case))->assertForbidden();
});

it('does not let an org-admin access the ACC intake queue', function (): void {
    ensureAccOrg();
    $own = Organization::factory()->create(['acronym' => 'OINT']);
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create(['name' => 'Service Delivery']);
    $intake = Grievance::factory()->inState(GrievanceState::Submitted)->create([
        'grievance_type_id' => $type->id,
    ]);

    actingAs($admin)->get(route('admin.grievances.show', $intake))->assertForbidden();
});
