<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\OrgGrievanceType;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Grievance\Services\InvalidTransition;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupCategorizationPerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.create', 'grievance.update',
        'grievance.transition', 'grievance.review', 'grievance.assign',
        'user.viewAny', 'user.view',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('acc-reviewer')->givePermissionTo(array_filter($perms, fn ($p) => str_starts_with($p, 'grievance.')));
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo(array_filter($perms, fn ($p) => str_starts_with($p, 'grievance.')));
    Role::findOrCreate('organization-officer')->givePermissionTo([
        'grievance.viewAny', 'grievance.view', 'grievance.transition', 'grievance.update',
    ]);
}

function makeAccOrg(): Organization
{
    return Organization::firstOrCreate(['acronym' => 'ACC'], ['name' => 'Anti-Corruption Commission']);
}

function makeNonAccOrg(): Organization
{
    return Organization::factory()->create(['acronym' => 'MH'.rand(10, 99)]);
}

function makeGrievance(GrievanceState $state = GrievanceState::Submitted): Grievance
{
    return Grievance::factory()->inState($state)->create([
        'grievance_type_id' => GrievanceType::factory()->create()->id,
    ]);
}

beforeEach(function (): void {
    setupCategorizationPerms();
    config(['grm.acc_acronym' => 'ACC']);
});

// 1. ACC reviewer can accept a grievance in under_review
it('ACC reviewer can accept a grievance in under_review', function (): void {
    $acc = makeAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');

    $grievance = makeGrievance(GrievanceState::UnderReview);

    actingAs($reviewer)
        ->post(route('admin.grievances.accept', $grievance))
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::Accepted);
    expect($grievance->fresh()->accepted_at)->not->toBeNull();
});

// 2. ACC reviewer can reject with a reason
it('ACC reviewer can reject a grievance with a reason', function (): void {
    $acc = makeAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');

    $grievance = makeGrievance(GrievanceState::UnderReview);

    actingAs($reviewer)
        ->post(route('admin.grievances.reject', $grievance), ['reason' => 'Not within mandate.'])
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::Rejected);
    expect($grievance->fresh()->review_comment)->toBe('Not within mandate.');
});

// 3. Rejected grievance cannot be transitioned to any other state
it('rejected grievance cannot be transitioned', function (): void {
    $grievance = makeGrievance(GrievanceState::Rejected);

    expect(fn () => app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::Accepted))
        ->toThrow(InvalidTransition::class);
    expect(fn () => app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::InProgress))
        ->toThrow(InvalidTransition::class);
});

// 4. Rejected grievance panels inaccessible (transition endpoint returns 403)
it('rejected grievance transition returns 403', function (): void {
    $acc = makeAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $grievance = makeGrievance(GrievanceState::Rejected);

    actingAs($reviewer)
        ->post(route('admin.grievances.accept', $grievance))
        ->assertForbidden();
});

// 5. ACC reviewer categorizes as corruption → org auto-locked to ACC
it('categorization as corruption forces ACC org', function (): void {
    $acc = makeAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');

    $grievance = makeGrievance(GrievanceState::Accepted);

    actingAs($reviewer)
        ->put(route('admin.grievances.categorize', $grievance), [
            'category' => 'corruption',
            'classified_organization_id' => $acc->id,
        ])
        ->assertRedirect();

    $g = $grievance->fresh();
    expect($g->category)->toBe('corruption');
    expect($g->classified_organization_id)->toBe($acc->id);
    expect($g->state)->toBe(GrievanceState::Assigned);
});

// 6. Categorize as administrative → any non-ACC org
it('categorization as administrative assigns to non-ACC org', function (): void {
    $acc = makeAccOrg();
    $nonAcc = makeNonAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');

    $grievance = makeGrievance(GrievanceState::Accepted);

    actingAs($reviewer)
        ->put(route('admin.grievances.categorize', $grievance), [
            'category' => 'administrative',
            'classified_organization_id' => $nonAcc->id,
        ])
        ->assertRedirect();

    $g = $grievance->fresh();
    expect($g->category)->toBe('administrative');
    expect($g->classified_organization_id)->toBe($nonAcc->id);
    expect($g->state)->toBe(GrievanceState::Assigned);
});

// 7. Category immutable after categorized (non-super-admin cannot recategorize)
it('category cannot be changed after categorized', function (): void {
    $acc = makeAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');

    $grievance = makeGrievance(GrievanceState::Assigned);
    $grievance->update(['category' => 'corruption', 'classified_organization_id' => $acc->id]);

    // The classify policy requires state=accepted; assigned state fails.
    actingAs($reviewer)
        ->put(route('admin.grievances.categorize', $grievance), [
            'category' => 'administrative',
            'classified_organization_id' => $acc->id,
        ])
        ->assertForbidden();
});

// 8. org-admin of owning org can set org sub-classification
it('org-admin sets org sub-classification', function (): void {
    $org = makeNonAccOrg();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = OrgGrievanceType::create(['organization_id' => $org->id, 'label' => 'Payments']);
    $grievance = makeGrievance(GrievanceState::Assigned);
    $grievance->update(['classified_organization_id' => $org->id, 'category' => 'administrative']);

    actingAs($admin)
        ->put(route('admin.grievances.orgClassify', $grievance), [
            'org_classification_id' => $type->id,
        ])
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::InProgress);
    expect($grievance->fresh()->org_classification_id)->toBe($type->id);
});

// 9. org-admin of DIFFERENT org cannot set classification
it('different org admin cannot set org sub-classification', function (): void {
    $org = makeNonAccOrg();
    $otherOrg = makeNonAccOrg();
    $admin = User::factory()->create(['organization_id' => $otherOrg->id]);
    $admin->assignRole('org-admin');

    $type = OrgGrievanceType::create(['organization_id' => $org->id, 'label' => 'X']);
    $grievance = makeGrievance(GrievanceState::Assigned);
    $grievance->update(['classified_organization_id' => $org->id, 'category' => 'administrative']);

    actingAs($admin)
        ->put(route('admin.grievances.orgClassify', $grievance), [
            'org_classification_id' => $type->id,
        ])
        ->assertForbidden();
});

// 10. Org sub-classification transitions to in_progress
it('org classification moves case to in_progress', function (): void {
    $org = makeNonAccOrg();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('grm-officer');

    $type = OrgGrievanceType::create(['organization_id' => $org->id, 'label' => 'SIM Issues']);
    $grievance = makeGrievance(GrievanceState::Assigned);
    $grievance->update(['classified_organization_id' => $org->id, 'category' => 'administrative']);

    actingAs($admin)
        ->put(route('admin.grievances.orgClassify', $grievance), [
            'org_classification_id' => $type->id,
        ])
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::InProgress);
});

// 11. Org with no types → workflow refuses (no type to set)
it('org with no types cannot reach in_progress via this path', function (): void {
    $org = makeNonAccOrg();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    // No OrgGrievanceType exists for this org.
    $grievance = makeGrievance(GrievanceState::Assigned);
    $grievance->update(['classified_organization_id' => $org->id, 'category' => 'administrative']);

    // The FormRequest rejects a non-existent org_classification_id.
    actingAs($admin)
        ->put(route('admin.grievances.orgClassify', $grievance), [
            'org_classification_id' => 999999,
        ])
        ->assertSessionHasErrors('org_classification_id');

    expect($grievance->fresh()->state)->toBe(GrievanceState::Assigned);
});

// 12. org-admin can manage grievance types for their org
it('org-admin can add and deactivate a grievance type', function (): void {
    $org = makeNonAccOrg();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    // Add.
    actingAs($admin)
        ->post(route('admin.org.grievance-types.store'), ['label' => 'Test Type'])
        ->assertRedirect();

    $type = OrgGrievanceType::where('organization_id', $org->id)->where('label', 'Test Type')->firstOrFail();
    expect($type->active)->toBeTrue();

    // Deactivate.
    actingAs($admin)
        ->patch(route('admin.org.grievance-types.update', $type), ['active' => false])
        ->assertRedirect();

    expect($type->fresh()->active)->toBeFalse();
});

// 13. org-admin cannot manage types for another org
it('org-admin cannot manage another org types', function (): void {
    $org = makeNonAccOrg();
    $otherOrg = makeNonAccOrg();
    $admin = User::factory()->create(['organization_id' => $otherOrg->id]);
    $admin->assignRole('org-admin');

    $type = OrgGrievanceType::create(['organization_id' => $org->id, 'label' => 'Foreign']);

    actingAs($admin)
        ->patch(route('admin.org.grievance-types.update', $type), ['active' => false])
        ->assertForbidden();

    actingAs($admin)
        ->delete(route('admin.org.grievance-types.destroy', $type))
        ->assertForbidden();
});

// 14. Full happy path
it('full happy path: submitted through to in_progress', function (): void {
    $acc = makeAccOrg();
    $targetOrg = makeNonAccOrg();
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $orgAdmin = User::factory()->create(['organization_id' => $targetOrg->id]);
    $orgAdmin->assignRole('org-admin');

    $grievance = makeGrievance(GrievanceState::Submitted);
    $wf = app(GrievanceWorkflow::class);

    // submitted → under_review
    $wf->transition($grievance, GrievanceState::UnderReview, $reviewer);
    expect($grievance->fresh()->state)->toBe(GrievanceState::UnderReview);

    // under_review → accepted
    actingAs($reviewer)->post(route('admin.grievances.accept', $grievance))->assertRedirect();
    expect($grievance->fresh()->state)->toBe(GrievanceState::Accepted);

    // accepted → categorized → assigned
    actingAs($reviewer)->put(route('admin.grievances.categorize', $grievance), [
        'category' => 'administrative',
        'classified_organization_id' => $targetOrg->id,
    ])->assertRedirect();
    expect($grievance->fresh()->state)->toBe(GrievanceState::Assigned);

    // assigned → org_classified → in_progress
    $type = OrgGrievanceType::create(['organization_id' => $targetOrg->id, 'label' => 'Inclusion Errors']);
    actingAs($orgAdmin)->put(route('admin.grievances.orgClassify', $grievance), [
        'org_classification_id' => $type->id,
    ])->assertRedirect();

    $final = $grievance->fresh();
    expect($final->state)->toBe(GrievanceState::InProgress);
    expect($final->category)->toBe('administrative');
    expect($final->classified_organization_id)->toBe($targetOrg->id);
    expect($final->org_classification_id)->toBe($type->id);
    expect($final->accepted_at)->not->toBeNull();
    expect($final->categorized_at)->not->toBeNull();
    expect($final->assigned_at)->not->toBeNull();
});
