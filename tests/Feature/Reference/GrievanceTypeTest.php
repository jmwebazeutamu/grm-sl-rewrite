<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Reference\Models\GrievanceType;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['grievance_type.viewAny', 'grievance_type.view', 'grievance_type.create', 'grievance_type.update', 'grievance_type.delete'] as $name) {
        Permission::findOrCreate($name);
    }
});

it('blocks users without permission', function (): void {
    actingAs(User::factory()->create())
        ->get(route('admin.reference.grievance-types.index'))
        ->assertForbidden();
});

it('lists grievance types', function (): void {
    $user = User::factory()->create()->givePermissionTo('grievance_type.viewAny');
    GrievanceType::factory()->count(3)->create();

    actingAs($user)
        ->get(route('admin.reference.grievance-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reference/GrievanceType/Index')->has('items.data', 3));
});

it('creates a grievance type', function (): void {
    $user = User::factory()->create()->givePermissionTo('grievance_type.create');

    actingAs($user)
        ->post(route('admin.reference.grievance-types.store'), ['name' => 'Service Delivery'])
        ->assertSessionHas('success');

    expect(GrievanceType::where('name', 'Service Delivery')->exists())->toBeTrue();
});

it('rejects duplicate name', function (): void {
    $user = User::factory()->create()->givePermissionTo('grievance_type.create');
    GrievanceType::factory()->create(['name' => 'Existing']);

    actingAs($user)
        ->post(route('admin.reference.grievance-types.store'), ['name' => 'Existing'])
        ->assertSessionHasErrors('name');
});

it('records authorship on create', function (): void {
    $user = User::factory()->create()->givePermissionTo('grievance_type.create');

    actingAs($user)
        ->post(route('admin.reference.grievance-types.store'), ['name' => 'Stamped']);

    $row = GrievanceType::where('name', 'Stamped')->first();
    expect($row->created_by_id)->toBe($user->id);
    expect($row->updated_by_id)->toBe($user->id);
});
