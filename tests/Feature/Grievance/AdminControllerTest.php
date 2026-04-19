<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['grievance.viewAny', 'grievance.view', 'grievance.transition', 'grievance.review'] as $p) {
        Permission::findOrCreate($p);
    }
});

it('blocks users without permission from the admin index', function (): void {
    actingAs(User::factory()->create())
        ->get(route('admin.grievances.index'))
        ->assertForbidden();
});

it('lists grievances for authorized users', function (): void {
    $user = User::factory()->create()->givePermissionTo('grievance.viewAny');
    Grievance::factory()->count(3)->create();

    actingAs($user)
        ->get(route('admin.grievances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Grievance/Admin/Index')->has('grievances.data', 3));
});

it('applies a transition via the admin endpoint', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['grievance.view', 'grievance.transition']);
    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->create();

    actingAs($user)
        ->post(route('admin.grievances.transition', $grievance), [
            'state' => GrievanceState::UnderReview->value,
            'note' => 'Picking this up',
        ])
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::UnderReview);
});

it('returns an error flash for an illegal transition', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['grievance.view', 'grievance.transition']);
    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->create();

    actingAs($user)
        ->post(route('admin.grievances.transition', $grievance), [
            'state' => GrievanceState::Closed->value,
        ])
        ->assertSessionHas('error');

    expect($grievance->fresh()->state)->toBe(GrievanceState::Submitted);
});

it('forbids transition on a terminal grievance', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['grievance.view', 'grievance.transition']);
    $grievance = Grievance::factory()->inState(GrievanceState::Closed)->create();

    actingAs($user)
        ->post(route('admin.grievances.transition', $grievance), [
            'state' => GrievanceState::InProgress->value,
        ])
        ->assertForbidden();
});
