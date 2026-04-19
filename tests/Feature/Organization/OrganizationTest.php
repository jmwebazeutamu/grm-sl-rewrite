<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['organization.viewAny', 'organization.view', 'organization.create', 'organization.update', 'organization.delete'] as $name) {
        Permission::findOrCreate($name);
    }
});

it('blocks users without permission', function (): void {
    actingAs(User::factory()->create())
        ->get(route('admin.organizations.index'))
        ->assertForbidden();
});

it('lists organizations', function (): void {
    $user = User::factory()->create()->givePermissionTo('organization.viewAny');
    Organization::factory()->count(3)->create();

    actingAs($user)
        ->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Organization/Index')->has('organizations.data', 3));
});

it('creates an organization with grievance types attached', function (): void {
    $user = User::factory()->create()->givePermissionTo('organization.create');
    $types = GrievanceType::factory()->count(2)->create();

    actingAs($user)
        ->post(route('admin.organizations.store'), [
            'name' => 'Ministry of Health',
            'acronym' => 'MoH',
            'grievance_type_ids' => $types->pluck('id')->toArray(),
        ])
        ->assertSessionHas('success');

    $org = Organization::where('name', 'Ministry of Health')->first();
    expect($org)->not->toBeNull();
    expect($org->grievanceTypes)->toHaveCount(2);
});

it('rejects organization being its own parent', function (): void {
    $user = User::factory()->create()->givePermissionTo('organization.update');
    $org = Organization::factory()->create();

    actingAs($user)
        ->put(route('admin.organizations.update', $org), [
            'name' => $org->name,
            'parent_id' => $org->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

it('soft deletes an organization', function (): void {
    $user = User::factory()->create()->givePermissionTo('organization.delete');
    $org = Organization::factory()->create();

    actingAs($user)->delete(route('admin.organizations.destroy', $org));

    expect($org->fresh()->deleted_at)->not->toBeNull();
});
