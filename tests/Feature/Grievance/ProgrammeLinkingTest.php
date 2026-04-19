<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\ProgrammeStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function setupProgrammePerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.update', 'grievance.transition', 'grievance.assign',
        'programme.viewAny', 'programme.view', 'programme.create', 'programme.update', 'programme.delete',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view']);
}

function makeProgramme(Organization $org, string $name, ProgrammeStatus $status = ProgrammeStatus::Active): Programme
{
    return Programme::create([
        'name' => $name,
        'acronym' => strtoupper(substr($name, 0, 3)),
        'organization_id' => $org->id,
        'status' => $status->value,
        'active' => $status === ProgrammeStatus::Active,
    ]);
}

beforeEach(function (): void {
    setupProgrammePerms();
    config(['services.recaptcha.secret' => null]);
});

//
// 1. Grievance with org + programme saves both
//

it('saves implementing_organization_id and programme_id on the grievance', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL01']);
    $prog = makeProgramme($org, 'Immunisation');
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Case with programme',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'implementing_organization_id' => $org->id,
        'programme_id' => $prog->id,
    ])->assertRedirect();

    $g = Grievance::where('summary', 'Case with programme')->firstOrFail();
    expect($g->implementing_organization_id)->toBe($org->id);
    expect($g->programme_id)->toBe($prog->id);
});

//
// 2. Without org/programme — saves null
//

it('saves nulls when implementing org and programme are not provided', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Minimal case',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
    ])->assertRedirect();

    $g = Grievance::where('summary', 'Minimal case')->firstOrFail();
    expect($g->implementing_organization_id)->toBeNull();
    expect($g->programme_id)->toBeNull();
});

//
// 3. Cross-org programme fails validation
//

it('rejects a programme_id that belongs to a different org', function (): void {
    $own = Organization::factory()->create(['acronym' => 'PL03']);
    $other = Organization::factory()->create(['acronym' => 'PL03B']);
    $otherProg = makeProgramme($other, 'Other-Org Programme');
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Cross-org attempt',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'implementing_organization_id' => $own->id,
        'programme_id' => $otherProg->id,
    ])->assertSessionHasErrors('programme_id');

    expect(Grievance::where('summary', 'Cross-org attempt')->exists())->toBeFalse();
});

//
// 4. Closed programme fails validation
//

it('rejects a closed programme', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL04']);
    $closed = makeProgramme($org, 'Closed Prog', ProgrammeStatus::Closed);
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Closed attempt',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'implementing_organization_id' => $org->id,
        'programme_id' => $closed->id,
    ])->assertSessionHasErrors('programme_id');

    expect(Grievance::where('summary', 'Closed attempt')->exists())->toBeFalse();
});

//
// 5. Show page payload exposes implementing org + programme
//

it('exposes implementing_organization and programme via the admin Show payload', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL05']);
    $prog = makeProgramme($org, 'Water Supply');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'implementing_organization_id' => $org->id,
        'programme_id' => $prog->id,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.show', $case))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('grievance.data.implementing_organization.name', $org->name)
            ->where('grievance.data.programme.name', 'Water Supply')
        );
});

//
// 6. Show page — null values serialize as null (frontend renders "—")
//

it('exposes null implementing_organization and programme when unset', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL06']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.show', $case))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('grievance.data.implementing_organization', null)
            ->where('grievance.data.programme', null)
        );
});

//
// 7. Org-admin can create a programme for own org
//

it('lets org-admin create a programme for their own org', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL07']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->post(route('admin.organizations.programmes.store', $org), [
            'name' => 'Own Prog',
            'acronym' => 'OP',
            'status' => 'active',
        ])
        ->assertRedirect();

    $p = Programme::where('name', 'Own Prog')->firstOrFail();
    expect($p->organization_id)->toBe($org->id);
    expect($p->status->value)->toBe('active');
});

//
// 8. Org-admin cannot create programme for another org
//

it('forces org-admin programme creation back to their own org', function (): void {
    $own = Organization::factory()->create(['acronym' => 'PL08']);
    $other = Organization::factory()->create(['acronym' => 'PL08B']);
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->post(route('admin.organizations.programmes.store', $other), [
            'name' => 'Boundary',
            'status' => 'active',
        ])
        ->assertRedirect();

    // Controller forces target org back to actor's own for non-super users.
    expect(Programme::where('name', 'Boundary')->first()->organization_id)->toBe($own->id);
});

//
// 9. Org-admin can update name, acronym, status
//

it('lets org-admin update name/acronym/status of own org programme', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL09']);
    $prog = makeProgramme($org, 'Original Name');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->patch(route('admin.organizations.programmes.update', [$org, $prog]), [
            'name' => 'Renamed',
            'acronym' => 'RN',
            'status' => 'closed',
        ])
        ->assertRedirect();

    $p = $prog->fresh();
    expect($p->name)->toBe('Renamed');
    expect($p->acronym)->toBe('RN');
    expect($p->status->value)->toBe('closed');
});

//
// 10. Org-admin cannot update another org's programme
//

it('blocks org-admin from updating another org programme', function (): void {
    $own = Organization::factory()->create(['acronym' => 'PL10']);
    $other = Organization::factory()->create(['acronym' => 'PL10B']);
    $otherProg = makeProgramme($other, 'Theirs');
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->patch(route('admin.organizations.programmes.update', [$other, $otherProg]), [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();

    expect($otherProg->fresh()->name)->toBe('Theirs');
});

//
// 11. Closed programme absent from public form dropdown
//

it('omits closed programmes from the public submission form', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL11']);
    makeProgramme($org, 'Active Programme');
    makeProgramme($org, 'Closed Programme', ProgrammeStatus::Closed);

    get(route('grievances.public.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('programmes', fn ($list) => collect($list)->pluck('name')->contains('Active Programme')
                && ! collect($list)->pluck('name')->contains('Closed Programme'))
        );
});

//
// 12. Active programme appears in dropdown with correct org link
//

it('surfaces active programmes with organization_id so the frontend can filter', function (): void {
    $orgA = Organization::factory()->create(['acronym' => 'PL12A']);
    $orgB = Organization::factory()->create(['acronym' => 'PL12B']);
    makeProgramme($orgA, 'Alpha');
    makeProgramme($orgB, 'Bravo');

    get(route('grievances.public.create'))
        ->assertOk()
        ->assertInertia(function ($page) use ($orgA, $orgB): bool {
            $programmes = collect($page->toArray()['props']['programmes'] ?? []);
            $alpha = $programmes->firstWhere('name', 'Alpha');
            $bravo = $programmes->firstWhere('name', 'Bravo');
            expect($alpha['organization_id'])->toBe($orgA->id);
            expect($bravo['organization_id'])->toBe($orgB->id);

            return true;
        });
});

//
// 13. Changing org resets programme (simulated at the request layer)
//

it('accepts a submission where programme is null after org change', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL13']);
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Org without programme',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'implementing_organization_id' => $org->id,
        'programme_id' => null,
    ])->assertRedirect();

    $g = Grievance::where('summary', 'Org without programme')->firstOrFail();
    expect($g->implementing_organization_id)->toBe($org->id);
    expect($g->programme_id)->toBeNull();
});

//
// 14. Super-admin can manage programmes for any org
//

it('lets super-admin create a programme for any org', function (): void {
    $other = Organization::factory()->create(['acronym' => 'PL14']);
    $super = User::factory()->create();
    $super->assignRole('super-admin');

    actingAs($super)
        ->post(route('admin.organizations.programmes.store', $other), [
            'name' => 'Super Made',
            'acronym' => 'SM',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Programme::where('name', 'Super Made')->first()->organization_id)->toBe($other->id);
});

//
// 15a. Grm-officer cannot CUD programmes
//

it('blocks grm-officer from creating, updating, or deleting a programme', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL15A']);
    $prog = makeProgramme($org, 'GRM Restricted');
    $grm = User::factory()->create(['organization_id' => $org->id]);
    $grm->assignRole('grm-officer');

    actingAs($grm)
        ->post(route('admin.organizations.programmes.store', $org), [
            'name' => 'GRM Attempt', 'status' => 'active',
        ])
        ->assertForbidden();

    actingAs($grm)
        ->patch(route('admin.organizations.programmes.update', [$org, $prog]), [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();

    actingAs($grm)
        ->delete(route('admin.organizations.programmes.destroy', [$org, $prog]))
        ->assertForbidden();
});

//
// 15b. Referential integrity — programme linked to a grievance cannot be deleted
//

it('blocks delete of a programme linked to a grievance', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL15B']);
    $prog = makeProgramme($org, 'In Use');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'implementing_organization_id' => $org->id,
        'programme_id' => $prog->id,
    ]);

    actingAs($admin)
        ->delete(route('admin.organizations.programmes.destroy', [$org, $prog]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Programme::find($prog->id))->not->toBeNull();
});

//
// 15c. Referential integrity — programme linked only to a suspect is also protected
//

it('blocks delete of a programme referenced by a suspect record', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL15C']);
    $prog = makeProgramme($org, 'Beneficiary Linked');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);
    $case->suspects()->create([
        'first_name' => 'Linked', 'last_name' => 'User',
        'is_beneficiary' => true,
        'programme_id' => $prog->id,
        'implementing_organization_id' => $org->id,
    ]);

    actingAs($admin)
        ->delete(route('admin.organizations.programmes.destroy', [$org, $prog]))
        ->assertSessionHas('error');

    expect(Programme::find($prog->id))->not->toBeNull();
});

//
// 15d. Unreferenced programme can be deleted normally
//

it('allows delete of a programme with no references', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL15D']);
    $prog = makeProgramme($org, 'Lonely Programme');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->delete(route('admin.organizations.programmes.destroy', [$org, $prog]))
        ->assertSessionHas('success');

    expect(Programme::find($prog->id))->toBeNull();
});

//
// 15. Programme with null acronym renders as "—" in management table
//

it('serialises a null acronym for the management table', function (): void {
    $org = Organization::factory()->create(['acronym' => 'PL15']);
    Programme::create([
        'name' => 'No Acronym',
        'acronym' => null,
        'organization_id' => $org->id,
        'status' => 'active',
        'active' => true,
    ]);

    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->get(route('admin.programmes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OrgSettings/Programmes/Index')
            ->where('programmes', fn ($rows) => collect($rows)->firstWhere('name', 'No Acronym')['acronym'] === null)
        );
});
