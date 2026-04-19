<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Section;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['locality.viewAny', 'locality.view', 'locality.create', 'locality.update', 'locality.delete'] as $name) {
        Permission::findOrCreate($name);
    }
});

it('blocks anonymous users', function (): void {
    $this->get(route('admin.localities.index'))->assertRedirect(route('login'));
});

it('blocks users without permission', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('admin.localities.index'))
        ->assertForbidden();
});

it('lists localities for authorized users', function (): void {
    $user = User::factory()->create()->givePermissionTo('locality.viewAny');
    $section = Section::factory()->create();
    Locality::factory()->count(3)->for($section)->create();

    actingAs($user)
        ->get(route('admin.localities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Locality/Index')
            ->has('localities.data', 3));
});

it('creates a locality', function (): void {
    $user = User::factory()->create()->givePermissionTo('locality.create');
    $section = Section::factory()->create();

    actingAs($user)
        ->post(route('admin.localities.store'), [
            'name' => 'Kissy',
            'section_id' => $section->id,
        ])
        ->assertSessionHas('success');

    expect(Locality::where('name', 'Kissy')->exists())->toBeTrue();
});

it('rejects duplicate locality within a section', function (): void {
    $user = User::factory()->create()->givePermissionTo('locality.create');
    $section = Section::factory()->create();
    Locality::factory()->for($section)->create(['name' => 'Kissy']);

    actingAs($user)
        ->post(route('admin.localities.store'), [
            'name' => 'Kissy',
            'section_id' => $section->id,
        ])
        ->assertSessionHasErrors('name');
});
