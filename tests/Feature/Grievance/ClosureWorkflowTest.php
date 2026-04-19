<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceReopened;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceStatusHistory;
use App\Domain\Grievance\Notifications\GrievanceReopenedNotification;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Grievance\Services\InvalidTransition;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupClosurePerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.create', 'grievance.update',
        'grievance.transition', 'grievance.assign', 'grievance.review',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo([
        'grievance.viewAny', 'grievance.view', 'grievance.update', 'grievance.transition',
    ]);
}

function makeClosureOrg(string $acronym): Organization
{
    return Organization::factory()->create(['acronym' => $acronym]);
}

function closureMakeAccOrg(): Organization
{
    return Organization::firstOrCreate(['acronym' => 'ACC'], ['name' => 'Anti-Corruption Commission']);
}

function makeInProgressCase(Organization $org, ?User $assigned = null): Grievance
{
    $type = GrievanceType::factory()->create();

    return Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'assigned_officer_id' => $assigned?->id,
    ]);
}

beforeEach(function (): void {
    setupClosurePerms();
});

//
// 1. Org can transition case → resolved
//

it('lets org user transition in_progress to resolved', function (): void {
    $org = makeClosureOrg('CL01');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeInProgressCase($org);

    actingAs($officer)
        ->post(route('admin.grievances.transition', $case), [
            'state' => GrievanceState::Resolved->value,
            'note' => 'Resolution posted',
        ])
        ->assertRedirect();

    expect($case->fresh()->state)->toBe(GrievanceState::Resolved);
});

//
// 2. Org cannot post actions in resolved
//

it('blocks org user from posting actions when state is resolved', function (): void {
    $org = makeClosureOrg('CL02');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeInProgressCase($org);
    app(GrievanceWorkflow::class)->transition($case, GrievanceState::Resolved, $officer);

    actingAs($officer)
        ->post(route('admin.grievances.actions.store', $case), [
            'type' => ActionType::Update->value,
            'body' => 'Trying to post after resolved',
        ])
        ->assertForbidden();
});

//
// 3. Org cannot upload files in resolved
//

it('blocks org user from uploading when state is resolved', function (): void {
    Storage::fake('local');
    $org = makeClosureOrg('CL03');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeInProgressCase($org);
    app(GrievanceWorkflow::class)->transition($case, GrievanceState::Resolved, $officer);

    actingAs($officer)
        ->post(route('admin.grievances.attachments.store', $case), [
            'files' => [UploadedFile::fake()->create('late.pdf', 50, 'application/pdf')],
        ])
        ->assertForbidden();
});

//
// 4. Org cannot post actions in under_admin_review
//

it('blocks org user from posting actions when state is under_admin_review', function (): void {
    $org = makeClosureOrg('CL04');
    closureMakeAccOrg();
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $officer);

    actingAs($officer)
        ->post(route('admin.grievances.actions.store', $case), [
            'type' => ActionType::Update->value,
            'body' => 'Also blocked here',
        ])
        ->assertForbidden();
});

//
// 5. ACC reviewer can begin closure review
//

it('lets ACC reviewer transition resolved to under_admin_review', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL05');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    app(GrievanceWorkflow::class)->transition($case, GrievanceState::Resolved, $officer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.begin', $case))
        ->assertRedirect();

    $case->refresh();
    expect($case->state)->toBe(GrievanceState::UnderAdminReview);
    expect($case->closure_reviewed_by_id)->toBe($reviewer->id);
    expect($case->closure_reviewed_at)->not->toBeNull();
});

//
// 6. Org-admin cannot begin closure review
//

it('blocks org-admin from beginning closure review', function (): void {
    $org = makeClosureOrg('CL06');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeInProgressCase($org);
    app(GrievanceWorkflow::class)->transition($case, GrievanceState::Resolved, $officer);

    actingAs($admin)
        ->post(route('admin.grievances.closure.begin', $case))
        ->assertForbidden();
});

//
// 7. Close requires closure_comment
//

it('rejects close request without closure_comment', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL07');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.close', $case), [
            'outcome' => 'satisfied',
        ])
        ->assertSessionHasErrors('closure_comment');

    expect($case->fresh()->state)->toBe(GrievanceState::UnderAdminReview);
});

//
// 8. Close transitions to closed + stamps closed_at
//

it('closes a grievance under_admin_review and stamps closed_at', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL08');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.close', $case), [
            'outcome' => 'satisfied',
            'closure_comment' => 'Complainant confirmed satisfied by phone.',
        ])
        ->assertRedirect();

    $case->refresh();
    expect($case->state)->toBe(GrievanceState::Closed);
    expect($case->closed_at)->not->toBeNull();
    expect($case->closure_comment)->toBe('Complainant confirmed satisfied by phone.');
});

//
// 9. closed is terminal — any further transition throws
//

it('rejects any transition attempt out of closed', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL09');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);
    $workflow->transition($case, GrievanceState::Closed, $reviewer);

    expect(fn () => $workflow->transition($case, GrievanceState::InProgress, $reviewer))
        ->toThrow(InvalidTransition::class);
});

//
// 10. EscalateAndReopen requires closure_comment
//

it('rejects escalate request without closure_comment', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL10');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.escalate', $case), [
            'outcome' => 'dissatisfied',
        ])
        ->assertSessionHasErrors('closure_comment');

    expect($case->fresh()->state)->toBe(GrievanceState::UnderAdminReview);
});

//
// 11. EscalateAndReopen ends as reopened, never escalated
//

it('ends as reopened after escalate and never persists escalated as resting state', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL11');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.escalate', $case), [
            'outcome' => 'dissatisfied',
            'closure_comment' => 'Complainant said the fix did not solve their problem.',
        ])
        ->assertRedirect();

    $case->refresh();
    expect($case->state)->toBe(GrievanceState::Reopened);
    expect($case->reopened_at)->not->toBeNull();
});

//
// 12. StatusHistory contains both escalated and reopened entries
//

it('writes both escalated and reopened rows to status history', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL12');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.escalate', $case), [
            'outcome' => 'dissatisfied',
            'closure_comment' => 'Complainant dissatisfied.',
        ]);

    $states = GrievanceStatusHistory::where('grievance_id', $case->id)
        ->get()
        ->pluck('to_state')
        ->map(fn ($s) => $s->value);
    expect($states->contains('escalated'))->toBeTrue();
    expect($states->contains('reopened'))->toBeTrue();
});

//
// 13. GrievanceStateChanged fires with final state reopened
//

it('dispatches GrievanceStateChanged with final state reopened after escalation', function (): void {
    Event::fake([GrievanceStateChanged::class]);

    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL13');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.escalate', $case), [
            'outcome' => 'dissatisfied',
            'closure_comment' => 'Dissatisfied.',
        ]);

    Event::assertDispatched(
        GrievanceStateChanged::class,
        fn ($event) => $event->to === GrievanceState::Reopened,
    );
});

//
// 14. Org grm-officer receives notification when case is reopened
//

it('notifies org grm-officer when a case is reopened', function (): void {
    Notification::fake();

    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL14');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);

    actingAs($reviewer)
        ->post(route('admin.grievances.closure.escalate', $case), [
            'outcome' => 'dissatisfied',
            'closure_comment' => 'Complainant requested escalation.',
        ]);

    Notification::assertSentTo($officer, GrievanceReopenedNotification::class);
});

//
// 15. Org user can post actions once reopened → in_progress
//

it('lets org user resume work on a reopened case (via in_progress)', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL15');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);
    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);
    $workflow->transition($case, GrievanceState::Escalated, $reviewer);
    $workflow->transition($case, GrievanceState::Reopened, $reviewer);

    actingAs($officer)
        ->post(route('admin.grievances.transition', $case), [
            'state' => GrievanceState::InProgress->value,
            'note' => 'Resuming work',
        ])
        ->assertRedirect();

    expect($case->fresh()->state)->toBe(GrievanceState::InProgress);
});

//
// 16. Full happy path — satisfied
//

it('completes the full satisfied path: in_progress → resolved → under_admin_review → closed', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL16');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);

    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);
    $workflow->transition($case, GrievanceState::Closed, $reviewer, 'All good.');

    expect($case->fresh()->state)->toBe(GrievanceState::Closed);
});

//
// 17. Full happy path — dissatisfied
//

it('completes the full dissatisfied path: in_progress → resolved → under_admin_review → reopened → in_progress', function (): void {
    $acc = closureMakeAccOrg();
    $org = makeClosureOrg('CL17');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    $case = makeInProgressCase($org);
    $workflow = app(GrievanceWorkflow::class);

    $workflow->transition($case, GrievanceState::Resolved, $officer);
    $workflow->transition($case, GrievanceState::UnderAdminReview, $reviewer);
    $workflow->transition($case, GrievanceState::Escalated, $reviewer, 'Not satisfied.');
    $workflow->transition($case, GrievanceState::Reopened, $reviewer);
    $workflow->transition($case, GrievanceState::InProgress, $officer);

    expect($case->fresh()->state)->toBe(GrievanceState::InProgress);
});

//
// 18. Super-admin bypasses read-only on resolved
//

it('lets super-admin bypass read-only guard and post actions on a resolved case', function (): void {
    $org = makeClosureOrg('CL18');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('super-admin');
    $case = makeInProgressCase($org);
    app(GrievanceWorkflow::class)->transition($case, GrievanceState::Resolved, $admin);

    actingAs($admin)
        ->post(route('admin.grievances.actions.store', $case), [
            'type' => ActionType::Update->value,
            'body' => 'Super-admin override',
        ])
        ->assertRedirect();
});
