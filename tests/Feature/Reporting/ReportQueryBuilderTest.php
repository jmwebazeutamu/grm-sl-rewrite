<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Region;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reporting\Models\SavedReport;
use App\Domain\Reporting\Services\ReportQueryBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupReportPerms(): void
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
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view']);
}

function seedCountry(): void
{
    \DB::table('country')->insertOrIgnore([
        'id' => 1, 'name' => 'Sierra Leone', 'iso_code' => 'SLE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function reportOrg(string $acronym): Organization
{
    return Organization::factory()->create(['acronym' => $acronym, 'sla_days' => 30]);
}

function reportCase(Organization $org, int $daysAgo = 5, ?GrievanceState $state = null): Grievance
{
    return Grievance::factory()
        ->inState($state ?? GrievanceState::InProgress)
        ->create([
            'grievance_type_id' => GrievanceType::factory()->create()->id,
            'classified_organization_id' => $org->id,
            'created_at' => now()->subDays($daysAgo),
        ]);
}

beforeEach(function (): void {
    setupReportPerms();
    seedCountry();
});

//
// 1. Org user sees only own org's grievances
//

it('scopes preview results to the org user organisation', function (): void {
    $own = reportOrg('RQ01');
    $other = reportOrg('RQ01B');
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');
    reportCase($own);
    reportCase($other);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), ['fields' => ['ref']])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 2. ACC reviewer sees all orgs
//

it('shows all orgs for ACC reviewer', function (): void {
    $acc = Organization::firstOrCreate(['acronym' => 'ACC'], ['name' => 'ACC']);
    $orgA = reportOrg('RQ02A');
    $orgB = reportOrg('RQ02B');
    $reviewer = User::factory()->create(['organization_id' => $acc->id]);
    $reviewer->assignRole('acc-reviewer');
    reportCase($orgA);
    reportCase($orgB);

    $response = actingAs($reviewer)
        ->postJson(route('admin.reports.preview'), ['fields' => ['ref']])
        ->assertOk();

    expect($response->json('total'))->toBeGreaterThanOrEqual(2);
});

//
// 3. Registration date > filter
//

it('filters by registration date >', function (): void {
    $org = reportOrg('RQ03');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org, 10);
    reportCase($org, 1);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'submitted_at'],
            'filters' => [['field' => 'submitted_at', 'operator' => '>', 'value' => now()->subDays(5)->toDateString()]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 4. Registration date between filter
//

it('filters by registration date between', function (): void {
    $org = reportOrg('RQ04');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org, 20);
    reportCase($org, 5);
    reportCase($org, 1);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref'],
            'filters' => [['field' => 'submitted_at', 'operator' => 'between', 'value' => [
                now()->subDays(10)->toDateString(),
                now()->subDays(2)->toDateString(),
            ]]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 5. District in filter
//

it('filters by district in', function (): void {
    $org = reportOrg('RQ05');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $region = Region::firstOrCreate(['name' => 'EAST'], ['country_id' => 1]);
    $d1 = District::firstOrCreate(['name' => 'KAILAHUN', 'region_id' => $region->id]);
    $d2 = District::firstOrCreate(['name' => 'KENEMA', 'region_id' => $region->id]);

    $c1 = reportCase($org);
    $c1->update(['district_id' => $d1->id]);
    $c2 = reportCase($org);
    $c2->update(['district_id' => $d2->id]);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'district'],
            'filters' => [['field' => 'district', 'operator' => 'in', 'value' => [$d1->id]]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 6. Status in filter
//

it('filters by status in', function (): void {
    $org = reportOrg('RQ06');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org, 5, GrievanceState::InProgress);
    reportCase($org, 5, GrievanceState::Resolved);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'status'],
            'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['resolved']]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 7. Summary contains filter
//

it('filters by summary contains', function (): void {
    $org = reportOrg('RQ07');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $c = reportCase($org);
    $c->update(['summary' => 'Water supply disruption in Kailahun']);
    reportCase($org);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'summary'],
            'filters' => [['field' => 'summary', 'operator' => 'contains', 'value' => 'Kailahun']],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 8. Days in system > filter
//

it('filters by days_open greater than threshold', function (): void {
    $org = reportOrg('RQ08');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org, 40);
    reportCase($org, 2);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'days_open'],
            'filters' => [['field' => 'days_open', 'operator' => '>', 'value' => 30]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 9 + 10. Days to resolution threshold excludes unresolved
//

it('days_to_resolution filter only returns resolved grievances exceeding threshold', function (): void {
    $org = reportOrg('RQ09');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $type = GrievanceType::factory()->create();

    Grievance::factory()->inState(GrievanceState::Resolved)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'created_at' => now()->subDays(45),
        'resolved_at' => now()->subDays(5),
    ]);
    Grievance::factory()->inState(GrievanceState::Resolved)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
        'created_at' => now()->subDays(5),
        'resolved_at' => now()->subDays(2),
    ]);
    reportCase($org, 50);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'days_to_resolution'],
            'filters' => [['field' => 'days_to_resolution', 'operator' => '>', 'value' => 10]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 10. Days to resolution excludes unresolved
//

it('days_to_resolution filter excludes all unresolved grievances', function (): void {
    $org = reportOrg('RQ10');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org, 100, GrievanceState::InProgress);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref', 'days_to_resolution'],
            'filters' => [['field' => 'days_to_resolution', 'operator' => '>', 'value' => 0]],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(0);
});

//
// 11. Multiple filters are ANDed
//

it('combines multiple filters with AND', function (): void {
    $org = reportOrg('RQ11');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $c1 = reportCase($org, 5, GrievanceState::InProgress);
    $c1->update(['summary' => 'Needle in haystack']);
    $c2 = reportCase($org, 5, GrievanceState::Resolved);
    $c2->update(['summary' => 'Needle elsewhere']);
    reportCase($org, 5, GrievanceState::InProgress);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref'],
            'filters' => [
                ['field' => 'summary', 'operator' => 'contains', 'value' => 'Needle'],
                ['field' => 'status', 'operator' => '=', 'value' => 'in_progress'],
            ],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

//
// 12. Field selection limits output columns
//

it('only returns selected field columns in response', function (): void {
    $org = reportOrg('RQ12');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), ['fields' => ['ref', 'status']])
        ->assertOk();

    $row = $response->json('rows.0');
    expect($row)->toHaveKey('ref');
    expect($row)->toHaveKey('status');
    expect($row)->not->toHaveKey('summary');
});

//
// 13. CSV export has correct Content-Disposition
//

it('CSV export returns correct headers', function (): void {
    $org = reportOrg('RQ13');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org);

    $response = actingAs($admin)
        ->post(route('admin.reports.export'), ['fields' => ['ref', 'status']]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

//
// 14. CSV first row matches field labels
//

it('CSV first row matches selected field labels', function (): void {
    $org = reportOrg('RQ14');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org);

    $response = actingAs($admin)
        ->post(route('admin.reports.export'), ['fields' => ['ref', 'status']]);

    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    expect($lines[0])->toContain('Reference number');
    expect($lines[0])->toContain('Status');
});

//
// 15. CSV has one data row per grievance
//

it('CSV contains one data row per matching grievance', function (): void {
    $org = reportOrg('RQ15');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    reportCase($org);
    reportCase($org);

    $response = actingAs($admin)
        ->post(route('admin.reports.export'), ['fields' => ['ref']]);

    $content = $response->streamedContent();
    $lines = array_filter(explode("\n", trim($content)));
    expect(count($lines))->toBe(3); // header + 2 data rows
});

//
// 16. Org user cannot see other org's grievances
//

it('org user cannot see grievances from another org', function (): void {
    $own = reportOrg('RQ16A');
    $other = reportOrg('RQ16B');
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');
    reportCase($other);

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), ['fields' => ['ref']])
        ->assertOk();

    expect($response->json('total'))->toBe(0);
});

//
// 17. Save report stores fields + filters as JSON
//

it('saves report fields and filters as JSON', function (): void {
    $org = reportOrg('RQ17');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->post(route('admin.reports.saved.store'), [
            'name' => 'My Test Report',
            'fields' => ['ref', 'status'],
            'filters' => [['field' => 'status', 'operator' => '=', 'value' => 'in_progress']],
        ])
        ->assertRedirect();

    $report = SavedReport::where('name', 'My Test Report')->firstOrFail();
    expect($report->fields)->toBe(['ref', 'status']);
    expect($report->filters[0]['field'])->toBe('status');
    expect($report->organization_id)->toBe($org->id);
});

//
// 18. Loading a saved report returns correct config
//

it('loading a saved report returns correct fields and filters', function (): void {
    $org = reportOrg('RQ18');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    SavedReport::create([
        'name' => 'Loaded',
        'created_by_id' => $admin->id,
        'organization_id' => $org->id,
        'fields' => ['ref', 'district'],
        'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['in_progress']]],
    ]);

    actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('savedReports', fn ($list) => collect($list)->where('name', 'Loaded')->isNotEmpty())
        );
});

//
// 19. Cross-org cannot delete saved report
//

it('blocks org user from deleting another org saved report', function (): void {
    $orgA = reportOrg('RQ19A');
    $orgB = reportOrg('RQ19B');
    $adminA = User::factory()->create(['organization_id' => $orgA->id]);
    $adminA->assignRole('org-admin');
    $adminB = User::factory()->create(['organization_id' => $orgB->id]);
    $adminB->assignRole('org-admin');

    $report = SavedReport::create([
        'name' => 'OrgB Report',
        'created_by_id' => $adminB->id,
        'organization_id' => $orgB->id,
        'fields' => ['ref'],
        'filters' => [],
    ]);

    actingAs($adminA)
        ->delete(route('admin.reports.saved.destroy', $report))
        ->assertForbidden();

    expect(SavedReport::find($report->id))->not->toBeNull();
});

//
// 20. Empty result set returns empty array
//

it('returns empty result set without error when no grievances match', function (): void {
    $org = reportOrg('RQ20');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $response = actingAs($admin)
        ->postJson(route('admin.reports.preview'), [
            'fields' => ['ref'],
            'filters' => [['field' => 'summary', 'operator' => 'contains', 'value' => 'zzzznonexistent']],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(0);
    expect($response->json('rows'))->toBe([]);
});
