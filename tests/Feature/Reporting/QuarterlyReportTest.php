<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\OrgGrievanceType;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reporting\Services\QuarterlyReportAggregator;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function setupQrPerms(): void
{
    $perms = ['grievance.viewAny', 'grievance.view', 'grievance.update', 'grievance.transition', 'grievance.assign'];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view']);
}

function qrOrg(string $acronym): Organization
{
    return Organization::factory()->create(['acronym' => $acronym, 'sla_days' => 30]);
}

function qrType(): GrievanceType
{
    return GrievanceType::factory()->create();
}

function qrCase(Organization $org, string $created, ?string $resolved = null, ?Programme $prog = null, ?int $orgClassId = null): Grievance
{
    $state = $resolved ? GrievanceState::Resolved : GrievanceState::InProgress;

    return Grievance::factory()->inState($state)->create([
        'grievance_type_id' => qrType()->id,
        'classified_organization_id' => $org->id,
        'created_at' => $created,
        'resolved_at' => $resolved,
        'programme_id' => $prog?->id,
        'org_classification_id' => $orgClassId,
    ]);
}

beforeEach(function (): void {
    setupQrPerms();
});

//
// 1. Q1 resolved within 90 days
//

it('counts Q1 grievance resolved within 90 days as resolved_within_sla', function (): void {
    $org = qrOrg('QR01');
    qrCase($org, '2026-02-01', '2026-03-15');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');

    expect($q1['resolved_within_sla'])->toBe(1);
    expect($q1['unresolved_or_late'])->toBe(0);
});

//
// 2. Q1 resolved after 90 days counts as unresolved_or_late
//

it('counts Q1 grievance resolved after 90 days as unresolved_or_late', function (): void {
    $org = qrOrg('QR02');
    qrCase($org, '2026-01-01', '2026-06-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');

    expect($q1['resolved_within_sla'])->toBe(0);
    expect($q1['unresolved_or_late'])->toBe(1);
});

//
// 3. Still-open grievance counts as unresolved_or_late
//

it('counts an open Q1 grievance as unresolved_or_late', function (): void {
    $org = qrOrg('QR03');
    qrCase($org, '2026-02-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');

    expect($q1['unresolved_or_late'])->toBe(1);
});

//
// 4. Quarter determined by created_at, not resolved_at
//

it('assigns quarter by created_at not resolved_at', function (): void {
    $org = qrOrg('QR04');
    qrCase($org, '2026-03-20', '2026-04-15');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');
    $q2 = collect($data['overall'])->firstWhere('quarter', 'Q2');

    expect($q1['total'])->toBe(1);
    expect($q2['total'])->toBe(0);
});

//
// 5. Resolution rate 0 when total is 0
//

it('returns 0 resolution rate when total is 0', function (): void {
    $org = qrOrg('QR05');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');

    expect((float) $q1['resolution_rate'])->toBe(0.0);
    expect($q1['total'])->toBe(0);
});

//
// 6. availableYears returns only years with data
//

it('availableYears returns only years with grievance data', function (): void {
    $org = qrOrg('QR06');
    qrCase($org, '2025-06-01');
    qrCase($org, '2026-02-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    expect($data['available_years'])->toContain(2026);
    expect($data['available_years'])->toContain(2025);
});

//
// 7. Org user sees only own org data
//

it('org user sees only their org data', function (): void {
    $own = qrOrg('QR07A');
    $other = qrOrg('QR07B');
    qrCase($own, '2026-02-01', '2026-02-15');
    qrCase($other, '2026-02-01', '2026-02-15');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $own->id);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');

    expect($q1['total'])->toBe(1);
});

//
// 8. ACC reviewer sees all orgs with no org filter
//

it('returns all orgs data when orgId is null', function (): void {
    $orgA = qrOrg('QR08A');
    $orgB = qrOrg('QR08B');
    qrCase($orgA, '2026-02-01');
    qrCase($orgB, '2026-02-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, null);
    $q1 = collect($data['overall'])->firstWhere('quarter', 'Q1');

    expect($q1['total'])->toBeGreaterThanOrEqual(2);
});

//
// 9. byProgramme includes Unlinked
//

it('byProgramme includes Unlinked for null programme_id', function (): void {
    $org = qrOrg('QR09');
    qrCase($org, '2026-02-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $names = collect($data['by_programme'])->pluck('name');

    expect($names->contains('Unlinked'))->toBeTrue();
});

//
// 10. byClassification includes Unlinked
//

it('byClassification includes Unlinked for null org_classification_id', function (): void {
    $org = qrOrg('QR10');
    qrCase($org, '2026-02-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $names = collect($data['by_classification'])->pluck('name');

    expect($names->contains('Unlinked'))->toBeTrue();
});

//
// 11. Missing quarters are zero-filled
//

it('fills missing quarters with zero values', function (): void {
    $org = qrOrg('QR11');
    qrCase($org, '2026-02-01');

    $data = app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    $quarters = collect($data['overall'])->pluck('quarter');

    expect($quarters->all())->toBe(['Q1', 'Q2', 'Q3', 'Q4']);
    expect(collect($data['overall'])->firstWhere('quarter', 'Q3')['total'])->toBe(0);
});

//
// 12. Cache populated after first call
//

it('caches aggregate results', function (): void {
    $org = qrOrg('QR12');
    qrCase($org, '2026-02-01');

    Cache::flush();
    expect(Cache::has("quarterly-report:{$org->id}:2026"))->toBeFalse();

    app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    expect(Cache::has("quarterly-report:{$org->id}:2026"))->toBeTrue();
});

//
// 13. Cache invalidated on state change
//

it('invalidates quarterly cache on GrievanceStateChanged', function (): void {
    $org = qrOrg('QR13');
    $g = qrCase($org, '2026-02-01');

    app(QuarterlyReportAggregator::class)->aggregate(2026, $org->id);
    expect(Cache::has("quarterly-report:{$org->id}:2026"))->toBeTrue();

    GrievanceStateChanged::dispatch($g, GrievanceState::InProgress, GrievanceState::Resolved);

    expect(Cache::has("quarterly-report:{$org->id}:2026"))->toBeFalse();
});

//
// 14. Fetch endpoint returns correct JSON structure
//

it('quarterly data fetch returns correct JSON structure', function (): void {
    $org = qrOrg('QR14');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    qrCase($org, '2026-02-01');

    actingAs($admin)
        ->getJson(route('admin.reports.quarterly.data', ['year' => 2026]))
        ->assertOk()
        ->assertJsonStructure([
            'overall' => [['year', 'quarter', 'total', 'resolved_within_sla', 'resolution_rate']],
            'by_programme',
            'by_classification',
            'available_years',
        ]);
});

//
// 15. Export CSV returns correct headers
//

it('quarterly export returns CSV with correct Content-Disposition', function (): void {
    $org = qrOrg('QR15');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $response = actingAs($admin)->post(route('admin.reports.quarterly.export'), ['year' => 2026]);
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('quarterly-report-2026');
});

//
// 16. Org user cannot fetch data for another org
//

it('org user always gets their own org data regardless of org param', function (): void {
    $own = qrOrg('QR16A');
    $other = qrOrg('QR16B');
    $admin = User::factory()->create(['organization_id' => $own->id]);
    $admin->assignRole('org-admin');
    qrCase($own, '2026-02-01');
    qrCase($other, '2026-02-01');

    $response = actingAs($admin)
        ->getJson(route('admin.reports.quarterly.data', ['year' => 2026, 'organization_id' => $other->id]))
        ->assertOk();

    $q1 = collect($response->json('overall'))->firstWhere('quarter', 'Q1');
    expect($q1['total'])->toBe(1);
});
