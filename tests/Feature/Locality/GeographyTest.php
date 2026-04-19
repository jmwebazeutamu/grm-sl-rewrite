<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Database\Seeders\GeographySeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function setupGeographyPerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.update',
        'grievance.transition', 'grievance.assign',
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

function seedSeedData(): void
{
    \DB::table('country')->insertOrIgnore([
        'id' => 1, 'name' => 'Sierra Leone', 'iso_code' => 'SLE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function smallGeography(): array
{
    seedSeedData();
    $east = Region::firstOrCreate(['name' => 'EAST'], ['country_id' => 1]);
    $west = Region::firstOrCreate(['name' => 'WEST'], ['country_id' => 1]);
    $kailahun = District::firstOrCreate(['name' => 'KAILAHUN', 'region_id' => $east->id]);
    $kenema = District::firstOrCreate(['name' => 'KENEMA', 'region_id' => $east->id]);
    $luawa = Chiefdom::firstOrCreate(['name' => 'LUAWA', 'district_id' => $kailahun->id]);
    $section = Section::firstOrCreate(['name' => 'Nyandehun', 'chiefdom_id' => $luawa->id]);
    $locality = Locality::firstOrCreate(['name' => 'Kailahun Town', 'section_id' => $section->id]);

    return compact('east', 'west', 'kailahun', 'kenema', 'luawa', 'section', 'locality');
}

beforeEach(function (): void {
    setupGeographyPerms();
    config(['services.recaptcha.secret' => null]);
});

//
// 1 + 2. Seeder counts + idempotent
//

it('geography seeder produces the expected dataset counts', function (): void {
    seedSeedData();
    (new GeographySeeder)->run();

    expect(Region::count())->toBe(5);
    expect(District::count())->toBe(16);
    expect(Chiefdom::count())->toBeGreaterThanOrEqual(131);
    expect(Section::count())->toBeGreaterThanOrEqual(265);
    expect(Locality::count())->toBeGreaterThanOrEqual(319);
});

it('re-running the geography seeder does not create duplicates', function (): void {
    seedSeedData();
    (new GeographySeeder)->run();
    $before = [
        'r' => Region::count(), 'd' => District::count(),
        'c' => Chiefdom::count(), 's' => Section::count(), 'l' => Locality::count(),
    ];

    (new GeographySeeder)->run();

    expect(Region::count())->toBe($before['r']);
    expect(District::count())->toBe($before['d']);
    expect(Chiefdom::count())->toBe($before['c']);
    expect(Section::count())->toBe($before['s']);
    expect(Locality::count())->toBe($before['l']);
});

//
// 3-6. API endpoints return correct filtered data
//

it('/api/geography/districts returns only districts for the given region', function (): void {
    ['east' => $east, 'kailahun' => $k, 'kenema' => $ke, 'west' => $w] = smallGeography();

    $data = get('/api/geography/districts?region_id='.$east->id)
        ->assertOk()
        ->json();

    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain($k->id);
    expect($ids)->toContain($ke->id);
});

it('/api/geography/chiefdoms returns only chiefdoms for the given district', function (): void {
    ['kailahun' => $d, 'luawa' => $c] = smallGeography();

    $data = get('/api/geography/chiefdoms?district_id='.$d->id)
        ->assertOk()
        ->json();

    expect(collect($data)->pluck('id')->all())->toBe([$c->id]);
});

it('/api/geography/sections returns only sections for the given chiefdom', function (): void {
    ['luawa' => $c, 'section' => $s] = smallGeography();

    $data = get('/api/geography/sections?chiefdom_id='.$c->id)
        ->assertOk()
        ->json();

    expect(collect($data)->pluck('id')->all())->toBe([$s->id]);
});

it('/api/geography/localities returns only localities for the given section', function (): void {
    ['section' => $s, 'locality' => $l] = smallGeography();

    $data = get('/api/geography/localities?section_id='.$s->id)
        ->assertOk()
        ->json();

    expect(collect($data)->pluck('id')->all())->toBe([$l->id]);
});

//
// 7. API requires no auth
//

it('geography API endpoints do not require authentication', function (): void {
    smallGeography();

    get('/api/geography/districts?region_id=1')->assertOk();
    get('/api/geography/chiefdoms?district_id=1')->assertOk();
    get('/api/geography/sections?chiefdom_id=1')->assertOk();
    get('/api/geography/localities?section_id=1')->assertOk();
});

//
// 8. Empty array when no children
//

it('returns empty array (not 404) when the parent has no children', function (): void {
    seedSeedData();
    $region = Region::firstOrCreate(['name' => 'EMPTY'], ['country_id' => 1]);

    $response = get('/api/geography/districts?region_id='.$region->id)->assertOk();
    expect($response->json())->toBe([]);
});

//
// 9. Grievance with region + district only saves both FKs
//

it('saves region_id and district_id on the grievance when other levels are null', function (): void {
    ['east' => $east, 'kailahun' => $kailahun] = smallGeography();
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Partial location',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'region_id' => $east->id,
        'district_id' => $kailahun->id,
    ])->assertRedirect();

    $g = Grievance::where('summary', 'Partial location')->firstOrFail();
    expect($g->region_id)->toBe($east->id);
    expect($g->district_id)->toBe($kailahun->id);
    expect($g->chiefdom_id)->toBeNull();
    expect($g->section_id)->toBeNull();
    expect($g->locality_id)->toBeNull();
});

//
// 10. Grievance with all five levels
//

it('saves all five location levels when provided', function (): void {
    ['east' => $e, 'kailahun' => $d, 'luawa' => $c, 'section' => $s, 'locality' => $l] = smallGeography();
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Full location',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'region_id' => $e->id, 'district_id' => $d->id,
        'chiefdom_id' => $c->id, 'section_id' => $s->id, 'locality_id' => $l->id,
    ])->assertRedirect();

    $g = Grievance::where('summary', 'Full location')->firstOrFail();
    expect($g->locality_id)->toBe($l->id);
    expect($g->section_id)->toBe($s->id);
});

//
// 11. No location — all five nulls
//

it('saves all nulls when no location provided', function (): void {
    $type = GrievanceType::factory()->create();
    post(route('grievances.public.store'), [
        'summary' => 'No location',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
    ])->assertRedirect();

    $g = Grievance::where('summary', 'No location')->firstOrFail();
    expect($g->region_id)->toBeNull();
    expect($g->district_id)->toBeNull();
});

//
// 12. District from wrong region fails validation
//

it('rejects district_id that does not belong to the submitted region_id', function (): void {
    ['east' => $e, 'west' => $w] = smallGeography();
    $otherDistrict = District::firstOrCreate(['name' => 'WESTERN-D', 'region_id' => $w->id]);
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Cross-region',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'region_id' => $e->id,
        'district_id' => $otherDistrict->id,
    ])->assertSessionHasErrors('district_id');

    expect(Grievance::where('summary', 'Cross-region')->exists())->toBeFalse();
});

//
// 13. locationLabel accessor
//

it('locationLabel accessor returns the correct chain', function (): void {
    ['east' => $e, 'kailahun' => $d, 'luawa' => $c] = smallGeography();
    $type = GrievanceType::factory()->create();
    $g = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'region_id' => $e->id,
        'district_id' => $d->id,
        'chiefdom_id' => $c->id,
    ]);
    $g->load('region', 'district', 'chiefdom', 'section', 'locality');

    expect($g->locationLabel())->toBe('EAST > KAILAHUN > LUAWA');
});

//
// 14. Admin can PATCH location
//

it('lets super-admin patch grievance location via the dedicated endpoint', function (): void {
    ['east' => $e, 'kailahun' => $d] = smallGeography();
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
    ]);

    actingAs($admin)
        ->patch(route('admin.grievances.location.update', $case), [
            'region_id' => $e->id,
            'district_id' => $d->id,
        ])
        ->assertRedirect();

    expect($case->fresh()->region_id)->toBe($e->id);
    expect($case->fresh()->district_id)->toBe($d->id);
});

//
// 15. Org-admin can update location of own-org case
//

it('lets org-admin patch grievance location for their own org case', function (): void {
    ['east' => $e] = smallGeography();
    $org = Organization::factory()->create(['acronym' => 'LOC15']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);

    actingAs($admin)
        ->patch(route('admin.grievances.location.update', $case), ['region_id' => $e->id])
        ->assertRedirect();

    expect($case->fresh()->region_id)->toBe($e->id);
});

//
// 16. Organization-officer cannot update location (not assigned)
//

it('blocks an unassigned organization-officer from updating location', function (): void {
    ['east' => $e] = smallGeography();
    $org = Organization::factory()->create(['acronym' => 'LOC16']);
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('organization-officer');

    $type = GrievanceType::factory()->create();
    $case = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $org->id,
    ]);

    actingAs($officer)
        ->patch(route('admin.grievances.location.update', $case), ['region_id' => $e->id])
        ->assertForbidden();
});

//
// 17. Super-admin can add a chiefdom to an existing district
//

it('lets super-admin add a chiefdom to an existing district', function (): void {
    ['kailahun' => $d] = smallGeography();
    $super = User::factory()->create();
    $super->assignRole('super-admin');

    actingAs($super)
        ->post(route('admin.geography.store', 'chiefdoms'), [
            'name' => 'New Chiefdom',
            'parent_id' => $d->id,
        ])
        ->assertRedirect();

    expect(Chiefdom::where('name', 'New Chiefdom')->first()->district_id)->toBe($d->id);
});

//
// 18. Super-admin deletes childless locality
//

it('lets super-admin delete a locality with no children', function (): void {
    ['locality' => $l] = smallGeography();
    $super = User::factory()->create();
    $super->assignRole('super-admin');

    actingAs($super)
        ->delete(route('admin.geography.destroy', ['localities', $l->id]))
        ->assertRedirect();

    expect(Locality::find($l->id))->toBeNull();
});

//
// 19. Deleting a district with chiefdoms is blocked with count
//

it('blocks deleting a district that has chiefdoms', function (): void {
    ['kailahun' => $d] = smallGeography();
    $super = User::factory()->create();
    $super->assignRole('super-admin');

    actingAs($super)
        ->delete(route('admin.geography.destroy', ['districts', $d->id]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(District::find($d->id))->not->toBeNull();
});

//
// 20. Non-super-admin cannot access admin geography management
//

it('blocks org-admin from admin geography management', function (): void {
    smallGeography();
    $org = Organization::factory()->create(['acronym' => 'LOC20']);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');

    actingAs($admin)
        ->post(route('admin.geography.store', 'regions'), ['name' => 'X', 'parent_id' => 1])
        ->assertForbidden();
});
