<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\FeedbackRequested;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['grievance.view', 'grievance.transition'] as $p) {
        Permission::findOrCreate($p);
    }
});

it('posts an Update action without changing state', function (): void {
    $user = User::factory()->create()->givePermissionTo(['grievance.view', 'grievance.transition']);
    $grievance = Grievance::factory()->inState(GrievanceState::InProgress)->create();

    actingAs($user)
        ->post(route('admin.grievances.actions.store', $grievance), [
            'type' => ActionType::Update->value,
            'body' => 'Called the officer in charge.',
        ])
        ->assertRedirect();

    expect($grievance->fresh()->actions)->toHaveCount(1);
    expect($grievance->fresh()->state)->toBe(GrievanceState::InProgress);
});

// Auto-advance from UnderReview → InProgress was removed in the
// categorization pipeline. Actions can only be posted after in_progress.
// This test now verifies Investigate does NOT change state when posted
// from InProgress (it's just a regular action).
it('Investigate from InProgress does not change state', function (): void {
    $user = User::factory()->create()->givePermissionTo(['grievance.view', 'grievance.transition']);
    $grievance = Grievance::factory()->inState(GrievanceState::InProgress)->create();

    actingAs($user)
        ->post(route('admin.grievances.actions.store', $grievance), [
            'type' => ActionType::Investigate->value,
            'body' => 'Beginning investigation.',
        ])
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::InProgress);
});

it('auto-transitions to Resolved and issues a feedback token when Resolve is posted', function (): void {
    Event::fake([FeedbackRequested::class]);

    $user = User::factory()->create()->givePermissionTo(['grievance.view', 'grievance.transition']);
    $grievance = Grievance::factory()->inState(GrievanceState::InProgress)->create();

    actingAs($user)
        ->post(route('admin.grievances.actions.store', $grievance), [
            'type' => ActionType::Resolve->value,
            'body' => 'Issue resolved — payment released.',
        ])
        ->assertRedirect();

    expect($grievance->fresh()->state)->toBe(GrievanceState::Resolved);
    expect($grievance->fresh()->resolved_at)->not->toBeNull();
    Event::assertDispatched(FeedbackRequested::class);
});
