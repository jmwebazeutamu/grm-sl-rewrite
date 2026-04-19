<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function setupBeneficiaryPerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.update', 'grievance.transition', 'grievance.assign', 'grievance.review',
        'programme.viewAny', 'programme.view',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view']);
}

beforeEach(function (): void {
    setupBeneficiaryPerms();
    config(['services.recaptcha.secret' => null]);
});

function submitWithSuspects(array $suspects): Grievance
{
    $type = GrievanceType::factory()->create();
    post(route('grievances.public.store'), [
        'summary' => 'Beneficiary test case',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'suspects' => $suspects,
    ])->assertRedirect();

    return Grievance::with('suspects.programme', 'suspects.implementingOrganization')
        ->where('summary', 'Beneficiary test case')
        ->latest()
        ->firstOrFail();
}

//
// 1. Suspect marked as beneficiary saves is_beneficiary=true
//

it('saves is_beneficiary=true when suspect is a beneficiary', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN01']);
    $project = Programme::create(['name' => 'WASH', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);

    $g = submitWithSuspects([[
        'first_name' => 'Alice', 'last_name' => 'Doe', 'title' => 'Facilitator', 'phone_number' => '+232700',
        'is_beneficiary' => true,
        'programme_id' => $project->id,
        'implementing_organization_id' => $org->id,
    ]]);

    $s = $g->suspects->first();
    expect($s->is_beneficiary)->toBeTrue();
    expect($s->programme_id)->toBe($project->id);
});

//
// 2. is_beneficiary=false forces programme_id + implementing_org=null
//

it('forces programme_id and implementing_organization_id to null when is_beneficiary=false', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN02']);
    $project = Programme::create(['name' => 'Education', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);

    $g = submitWithSuspects([[
        'first_name' => 'Bob', 'last_name' => 'Doe', 'title' => 'Agent', 'phone_number' => '+232700',
        'is_beneficiary' => false,
        'programme_id' => $project->id, // attempted smuggle
        'implementing_organization_id' => $org->id,
    ]]);

    $s = $g->suspects->first();
    expect($s->is_beneficiary)->toBeFalse();
    expect($s->programme_id)->toBeNull();
    expect($s->implementing_organization_id)->toBeNull();
});

//
// 3. Valid project saves programme_id
//

it('saves programme_id when a valid project is selected', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN03']);
    $project = Programme::create(['name' => 'Cash Transfer', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);

    $g = submitWithSuspects([[
        'first_name' => 'Carol', 'last_name' => 'Doe', 'title' => 'Beneficiary',
        'is_beneficiary' => true,
        'programme_id' => $project->id,
        'implementing_organization_id' => $org->id,
    ]]);

    expect($g->suspects->first()->programme?->name)->toBe('Cash Transfer');
});

//
// 4. "Other" project (null) saves programme_id=null but keeps is_beneficiary=true
//

it('saves programme_id=null when Other is selected as project', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN04']);

    $g = submitWithSuspects([[
        'first_name' => 'Dan', 'last_name' => 'Doe', 'title' => 'Member',
        'is_beneficiary' => true,
        'programme_id' => null,
        'implementing_organization_id' => $org->id,
    ]]);

    $s = $g->suspects->first();
    expect($s->is_beneficiary)->toBeTrue();
    expect($s->programme_id)->toBeNull();
    expect($s->implementing_organization_id)->toBe($org->id);
});

//
// 5. Beneficiary without implementing org fails validation
//

it('rejects a beneficiary suspect without an implementing organization', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Missing impl org',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'suspects' => [[
            'first_name' => 'Eve', 'last_name' => 'Doe', 'title' => 'x',
            'is_beneficiary' => true,
            'programme_id' => null,
            'implementing_organization_id' => null,
        ]],
    ])->assertSessionHasErrors('suspects.0.implementing_organization_id');

    expect(Grievance::where('summary', 'Missing impl org')->exists())->toBeFalse();
});

//
// 6. Beneficiary flag saved independently per suspect
//

it('saves the beneficiary flag independently for each suspect', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN06']);
    $project = Programme::create(['name' => 'Livelihoods', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);

    $g = submitWithSuspects([
        [
            'first_name' => 'A', 'last_name' => 'One', 'title' => 't',
            'is_beneficiary' => true,
            'programme_id' => $project->id,
            'implementing_organization_id' => $org->id,
        ],
        [
            'first_name' => 'B', 'last_name' => 'Two', 'title' => 't',
            'is_beneficiary' => false,
        ],
    ]);

    $sA = $g->suspects->firstWhere('first_name', 'A');
    $sB = $g->suspects->firstWhere('first_name', 'B');
    expect($sA->is_beneficiary)->toBeTrue();
    expect($sB->is_beneficiary)->toBeFalse();
    expect($sB->programme_id)->toBeNull();
});

//
// 7. Show page payload includes beneficiary flag for beneficiary suspects
//

it('exposes is_beneficiary=true via the admin Show payload when applicable', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN07']);
    $project = Programme::create(['name' => 'Water', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);
    $case->suspects()->create([
        'first_name' => 'Frank', 'last_name' => 'Doe',
        'is_beneficiary' => true,
        'programme_id' => $project->id,
        'implementing_organization_id' => $org->id,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.show', $case))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('grievance.data.suspects.0.is_beneficiary', true)
            ->where('grievance.data.suspects.0.programme.name', 'Water')
        );
});

//
// 8. Show page payload does not include beneficiary badge when false
//

it('exposes is_beneficiary=false via the admin Show payload for non-beneficiaries', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN08']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);
    $case->suspects()->create([
        'first_name' => 'Grace', 'last_name' => 'Doe',
        'is_beneficiary' => false,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.show', $case))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('grievance.data.suspects.0.is_beneficiary', false)
            ->where('grievance.data.suspects.0.programme', null)
        );
});

//
// 9. Project with org_id auto-populates implementing org via SubmitGrievance
//

it('accepts the implementing org auto-populated from project', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN09']);
    $project = Programme::create(['name' => 'Protection', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);

    $g = submitWithSuspects([[
        'first_name' => 'Helen', 'last_name' => 'Doe',
        'is_beneficiary' => true,
        'programme_id' => $project->id,
        'implementing_organization_id' => $org->id,
    ]]);

    expect($g->suspects->first()->implementing_organization_id)->toBe($org->id);
});

//
// 10. Org-admin can add project for own org
//

it('lets org-admin create a programme for their own org', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN10']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->post(route('admin.organizations.programmes.store', $org), [
            'name' => 'My Project',
            'status' => 'active',
        ])
        ->assertRedirect();

    $p = Programme::where('name', 'My Project')->firstOrFail();
    expect($p->organization_id)->toBe($org->id);
    expect($p->status->value)->toBe('active');
});

//
// 11. Org-admin cannot add project for another org (org_id gets forced to own)
//

it('forces org-admin programme creation to their own org', function (): void {
    $own = Organization::factory()->create(['acronym' => 'BEN11']);
    $other = Organization::factory()->create(['acronym' => 'BEN11B']);
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');

    // Even if they POST to another org's endpoint, the controller forces
    // the target organization back to the actor's own for non-super users.
    actingAs($admin)
        ->post(route('admin.organizations.programmes.store', $other), [
            'name' => 'Boundary Project',
            'status' => 'active',
        ])
        ->assertRedirect();

    $p = Programme::where('name', 'Boundary Project')->firstOrFail();
    expect($p->organization_id)->toBe($own->id);
});

//
// 12. Super-admin can add project for any org
//

it('lets super-admin create a programme for any org', function (): void {
    $other = Organization::factory()->create(['acronym' => 'BEN12']);
    $super = User::factory()->create();
    $super->assignRole('super-admin');

    actingAs($super)
        ->post(route('admin.organizations.programmes.store', $other), [
            'name' => 'Super Project',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Programme::where('name', 'Super Project')->first()->organization_id)->toBe($other->id);
});

//
// 13. Deactivated project does not appear in public submission form
//

it('omits inactive projects from the public submission form', function (): void {
    $org = Organization::factory()->create(['acronym' => 'BEN13']);
    Programme::create(['name' => 'Live Project', 'organization_id' => $org->id, 'active' => true, 'status' => 'active']);
    Programme::create(['name' => 'Dead Project', 'organization_id' => $org->id, 'active' => false, 'status' => 'closed']);

    get(route('grievances.public.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('programmes', fn ($list) => collect($list)->pluck('name')->contains('Live Project')
                && ! collect($list)->pluck('name')->contains('Dead Project'))
        );
});
