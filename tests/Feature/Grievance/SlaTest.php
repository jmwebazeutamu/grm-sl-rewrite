<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\SlaCalculator;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupSlaPerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.update', 'grievance.transition', 'grievance.assign',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view']);
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
}

function slaCase(GrievanceState $state, int $daysAgo, ?int $resolvedDaysAgo = null, ?Organization $org = null, ?Programme $prog = null): Grievance
{
    $type = GrievanceType::factory()->create();

    return Grievance::factory()->inState($state)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org?->id,
        'implementing_organization_id' => $org?->id,
        'programme_id' => $prog?->id,
        'created_at' => now()->subDays($daysAgo),
        'resolved_at' => $resolvedDaysAgo !== null ? now()->subDays($resolvedDaysAgo) : null,
    ]);
}

beforeEach(function (): void {
    setupSlaPerms();
});

//
// 1. Green when < 80%
//

it('returns green when days open is below 80% of SLA', function (): void {
    $org = Organization::factory()->create(['sla_days' => 30]);
    $g = slaCase(GrievanceState::InProgress, 10, null, $org);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['status'])->toBe('green');
    expect($result['days_open'])->toBeLessThan(24);
});

//
// 2. Amber when >= 80% and < 100%
//

it('returns amber when days open is between 80% and 100% of SLA', function (): void {
    $org = Organization::factory()->create(['sla_days' => 30]);
    $g = slaCase(GrievanceState::InProgress, 25, null, $org);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['status'])->toBe('amber');
});

//
// 3. Red when >= 100%
//

it('returns red when days open exceeds SLA', function (): void {
    $org = Organization::factory()->create(['sla_days' => 30]);
    $g = slaCase(GrievanceState::InProgress, 35, null, $org);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['status'])->toBe('red');
});

//
// 4. Grey for resolved regardless of days
//

it('returns grey for resolved grievance regardless of days open', function (): void {
    $org = Organization::factory()->create(['sla_days' => 10]);
    $g = slaCase(GrievanceState::Resolved, 50, 0, $org);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['status'])->toBe('grey');
});

//
// 5. Programme SLA takes precedence
//

it('uses programme sla_days when set', function (): void {
    $org = Organization::factory()->create(['sla_days' => 30]);
    $prog = Programme::create([
        'name' => 'Fast Track', 'organization_id' => $org->id,
        'status' => 'active', 'active' => true, 'sla_days' => 10,
    ]);
    $g = slaCase(GrievanceState::InProgress, 5, null, $org, $prog);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['sla_days'])->toBe(10);
    expect($result['status'])->toBe('green');
});

//
// 6. Falls back to org SLA when programme has null
//

it('falls back to org sla_days when programme has null', function (): void {
    $org = Organization::factory()->create(['sla_days' => 20]);
    $prog = Programme::create([
        'name' => 'No Override', 'organization_id' => $org->id,
        'status' => 'active', 'active' => true, 'sla_days' => null,
    ]);
    $g = slaCase(GrievanceState::InProgress, 5, null, $org, $prog);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['sla_days'])->toBe(20);
});

//
// 7. Falls back to 30 when neither set
//

it('falls back to 30 days when both programme and org SLA are null', function (): void {
    $g = slaCase(GrievanceState::InProgress, 5);

    $result = app(SlaCalculator::class)->calculate($g);
    expect($result['sla_days'])->toBe(30);
});

//
// 8. Resource includes SLA fields in index response
//

it('includes SLA and classification fields in the grievance index response', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA08', 'sla_days' => 30]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $type = GrievanceType::factory()->create();
    Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('grievances.data.0.days_open')
            ->has('grievances.data.0.sla_days')
            ->has('grievances.data.0.sla_status')
        );
});

//
// 9. Resource does NOT include 'type' key (it still has grievance_type)
// Actually we keep grievance_type in the resource for Show — so this just tests
// that org_classification_label is present.
//

it('includes org_classification_label in the index payload', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA09', 'sla_days' => 30]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $type = GrievanceType::factory()->create();
    Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('grievances.data.0', fn ($g) => $g
                ->has('org_classification_label')
                ->has('programme_name')
                ->has('district_name')
                ->etc()
            )
        );
});

//
// 10. Org-admin can update org default SLA
//

it('lets org-admin update the organisation default SLA', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA10', 'sla_days' => 30]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->patch(route('admin.org.sla.updateOrg'), ['sla_days' => 45])
        ->assertRedirect();

    expect($org->fresh()->sla_days)->toBe(45);
});

//
// 11. SLA below 1 or above 365 fails validation
//

it('rejects SLA days outside 1-365 range', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA11', 'sla_days' => 30]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->patch(route('admin.org.sla.updateOrg'), ['sla_days' => 0])
        ->assertSessionHasErrors('sla_days');

    actingAs($admin)
        ->patch(route('admin.org.sla.updateOrg'), ['sla_days' => 400])
        ->assertSessionHasErrors('sla_days');
});

//
// 12. Org-admin can set programme SLA override
//

it('lets org-admin set a programme SLA override', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA12', 'sla_days' => 30]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $prog = Programme::create([
        'name' => 'Override Me', 'organization_id' => $org->id,
        'status' => 'active', 'active' => true,
    ]);

    actingAs($admin)
        ->patch(route('admin.org.sla.updateProgramme', $prog), ['sla_days' => 15])
        ->assertRedirect();

    expect($prog->fresh()->sla_days)->toBe(15);
});

//
// 13. Org-admin can clear programme SLA override
//

it('lets org-admin clear a programme SLA override', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA13', 'sla_days' => 30]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $prog = Programme::create([
        'name' => 'Clear Me', 'organization_id' => $org->id,
        'status' => 'active', 'active' => true, 'sla_days' => 15,
    ]);

    actingAs($admin)
        ->patch(route('admin.org.sla.updateProgramme', $prog), ['sla_days' => null])
        ->assertRedirect();

    expect($prog->fresh()->sla_days)->toBeNull();
});

//
// 14. Cross-org SLA update blocked
//

it('blocks org-admin of org A from updating SLA for org B programme', function (): void {
    $orgA = Organization::factory()->create(['acronym' => 'SLA14A']);
    $orgB = Organization::factory()->create(['acronym' => 'SLA14B']);
    $adminA = User::factory()->create(['organization_id' => $orgA->id]);
    $adminA->assignRole('org-admin');
    $progB = Programme::create([
        'name' => 'Theirs', 'organization_id' => $orgB->id,
        'status' => 'active', 'active' => true,
    ]);

    actingAs($adminA)
        ->patch(route('admin.org.sla.updateProgramme', $progB), ['sla_days' => 5])
        ->assertForbidden();
});

//
// 15. Organization-officer blocked
//

it('blocks organization-officer from accessing SLA settings', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA15']);
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('organization-officer');

    actingAs($officer)
        ->get(route('admin.org.sla.index'))
        ->assertForbidden();
});

//
// 16. SLA filter on index
//

it('client-side SLA filter is supported by the resource returning sla_status', function (): void {
    $org = Organization::factory()->create(['acronym' => 'SLA16', 'sla_days' => 10]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $type = GrievanceType::factory()->create();

    Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'created_at' => now()->subDays(1),
    ]);
    Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'created_at' => now()->subDays(15),
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $data = $page->toArray()['props']['grievances']['data'] ?? [];
            $statuses = collect($data)->pluck('sla_status')->unique()->sort()->values()->all();
            expect(count($statuses))->toBeGreaterThanOrEqual(2);
            expect($statuses)->toContain('green');
            expect($statuses)->toContain('red');
        });
});
