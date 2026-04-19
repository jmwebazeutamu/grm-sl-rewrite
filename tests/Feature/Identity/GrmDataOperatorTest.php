<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupDataOpPerms(): void
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
    Role::findOrCreate('acc-reviewer')->syncPermissions(Permission::whereIn('name', [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.review', 'grievance.transition', 'grievance.update', 'grievance.assign',
    ])->get());
    Role::findOrCreate('grm-data-operator')->syncPermissions(Permission::whereIn('name', [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.review', 'grievance.transition', 'grievance.update', 'grievance.assign',
    ])->get());
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
}

function dataOpAccOrg(): Organization
{
    return Organization::firstOrCreate(['acronym' => 'ACC'], ['name' => 'Anti-Corruption Commission']);
}

function dataOpUser(): User
{
    $acc = dataOpAccOrg();
    $u = User::factory()->create(['organization_id' => $acc->id]);
    $u->assignRole('grm-data-operator');

    return $u;
}

function dataOpCase(GrievanceState $state = GrievanceState::Submitted, ?Organization $org = null): Grievance
{
    return Grievance::factory()->inState($state)->create([
        'grievance_type_id' => GrievanceType::factory()->create()->id,
        'classified_organization_id' => $org?->id,
    ]);
}

beforeEach(function (): void {
    setupDataOpPerms();
});

// 1
it('grm-data-operator can view the grievance list', function (): void {
    $op = dataOpUser();
    dataOpCase();
    actingAs($op)->get(route('admin.grievances.index'))->assertOk();
});

// 2
it('grm-data-operator sees grievances from all orgs', function (): void {
    $op = dataOpUser();
    $orgA = Organization::factory()->create(['acronym' => 'DO02A']);
    $orgB = Organization::factory()->create(['acronym' => 'DO02B']);
    dataOpCase(GrievanceState::InProgress, $orgA);
    dataOpCase(GrievanceState::InProgress, $orgB);

    actingAs($op)->get(route('admin.grievances.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('grievances.data', fn ($d) => count($d) >= 2));
});

// 3
it('grm-data-operator can accept a grievance', function (): void {
    $op = dataOpUser();
    $case = dataOpCase(GrievanceState::UnderReview);

    actingAs($op)->post(route('admin.grievances.accept', $case))->assertRedirect();
    expect($case->fresh()->state)->toBe(GrievanceState::Accepted);
});

// 4
it('grm-data-operator can reject a grievance', function (): void {
    $op = dataOpUser();
    $case = dataOpCase(GrievanceState::UnderReview);

    actingAs($op)->post(route('admin.grievances.reject', $case), ['reason' => 'Out of scope'])->assertRedirect();
    expect($case->fresh()->state)->toBe(GrievanceState::Rejected);
});

// 5
it('grm-data-operator can categorize an accepted grievance', function (): void {
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO05']);
    $case = dataOpCase(GrievanceState::Accepted);

    actingAs($op)->put(route('admin.grievances.categorize', $case), [
        'category' => 'administrative',
        'classified_organization_id' => $org->id,
    ])->assertRedirect();

    expect($case->fresh()->category)->toBe('administrative');
});

// 6
it('grm-data-operator can assign a grievance to an organisation', function (): void {
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO06']);
    $case = dataOpCase(GrievanceState::InProgress, $org);
    $officer = User::factory()->create(['organization_id' => $org->id]);

    actingAs($op)->post(route('admin.grievances.assign', $case), ['officer_id' => $officer->id])->assertRedirect();
    expect($case->fresh()->assigned_officer_id)->toBe($officer->id);
});

// 7
it('grm-data-operator can begin closure review', function (): void {
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO07']);
    $case = dataOpCase(GrievanceState::InProgress, $org);
    app(GrievanceWorkflow::class)->transition($case, GrievanceState::Resolved, $op);

    actingAs($op)->post(route('admin.grievances.closure.begin', $case))->assertRedirect();
    expect($case->fresh()->state)->toBe(GrievanceState::UnderAdminReview);
});

// 8
it('grm-data-operator can close a grievance', function (): void {
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO08']);
    $case = dataOpCase(GrievanceState::InProgress, $org);
    $wf = app(GrievanceWorkflow::class);
    $wf->transition($case, GrievanceState::Resolved, $op);
    $wf->transition($case, GrievanceState::UnderAdminReview, $op);

    actingAs($op)->post(route('admin.grievances.closure.close', $case), [
        'closure_comment' => 'Complainant confirmed resolution.',
        'outcome' => 'satisfied',
    ])->assertRedirect();
    expect($case->fresh()->state)->toBe(GrievanceState::Closed);
});

// 9
it('grm-data-operator can escalate a grievance', function (): void {
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO09']);
    $case = dataOpCase(GrievanceState::InProgress, $org);
    $wf = app(GrievanceWorkflow::class);
    $wf->transition($case, GrievanceState::Resolved, $op);
    $wf->transition($case, GrievanceState::UnderAdminReview, $op);

    actingAs($op)->post(route('admin.grievances.closure.escalate', $case), [
        'closure_comment' => 'Complainant not satisfied.',
        'outcome' => 'dissatisfied',
    ])->assertRedirect();
    expect($case->fresh()->state)->toBe(GrievanceState::Reopened);
});

// 10
it('grm-data-operator can post a comment', function (): void {
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO10']);
    $case = dataOpCase(GrievanceState::InProgress, $org);

    actingAs($op)->post(route('admin.grievances.actions.store', $case), [
        'type' => ActionType::Update->value,
        'body' => 'Data operator note',
    ])->assertRedirect();
});

// 11
it('grm-data-operator can upload a file', function (): void {
    Storage::fake('local');
    $op = dataOpUser();
    $org = Organization::factory()->create(['acronym' => 'DO11']);
    $case = dataOpCase(GrievanceState::InProgress, $org);

    actingAs($op)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('note.pdf', 50, 'application/pdf')],
    ])->assertRedirect();
    expect($case->fresh()->attachments)->toHaveCount(1);
});

// 12
it('grm-data-operator cannot access user management', function (): void {
    $op = dataOpUser();
    actingAs($op)->get(route('admin.org.users.index'))->assertForbidden();
});

// 13
it('grm-data-operator cannot access organisation management', function (): void {
    $op = dataOpUser();
    actingAs($op)->get(route('admin.organizations.index'))->assertForbidden();
});

// 14
it('grm-data-operator cannot access geography management', function (): void {
    $op = dataOpUser();
    actingAs($op)->get(route('admin.geography.index'))->assertForbidden();
});

// 15
it('grm-data-operator cannot delete a grievance', function (): void {
    $op = dataOpUser();
    $case = dataOpCase();
    actingAs($op)->delete(route('admin.grievances.show', $case))->assertStatus(405);
});

// 16
it('grm-data-operator role has the correct permission set', function (): void {
    $role = Role::findByName('grm-data-operator');
    expect($role)->not->toBeNull();
    $perms = $role->permissions->pluck('name');
    expect($perms->contains('grievance.viewAny'))->toBeTrue();
    expect($perms->contains('grievance.review'))->toBeTrue();
    expect($perms->contains('grievance.assign'))->toBeTrue();
    expect($perms->contains('user.viewAny'))->toBeFalse();
});

// 17
it('acc-reviewer permissions are unchanged', function (): void {
    $perms = Role::findByName('acc-reviewer')->permissions->pluck('name')->sort()->values();
    expect($perms->contains('grievance.viewAny'))->toBeTrue();
    expect($perms->contains('grievance.review'))->toBeTrue();
    expect($perms->contains('grievance.assign'))->toBeTrue();
});
