<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Grievance\Services\InvalidTransition;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Event;

it('permits legal transitions in the new pipeline', function (): void {
    $workflow = app(GrievanceWorkflow::class);
    expect($workflow->canTransition(GrievanceState::Submitted, GrievanceState::UnderReview))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::UnderReview, GrievanceState::Accepted))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::Accepted, GrievanceState::Categorized))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::Categorized, GrievanceState::Assigned))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::Assigned, GrievanceState::OrgClassified))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::OrgClassified, GrievanceState::InProgress))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::InProgress, GrievanceState::Resolved))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::Resolved, GrievanceState::UnderAdminReview))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::UnderAdminReview, GrievanceState::Closed))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::UnderAdminReview, GrievanceState::Escalated))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::Escalated, GrievanceState::Reopened))->toBeTrue();
    expect($workflow->canTransition(GrievanceState::Reopened, GrievanceState::InProgress))->toBeTrue();
});

it('rejects illegal transitions', function (): void {
    $workflow = app(GrievanceWorkflow::class);
    expect($workflow->canTransition(GrievanceState::Submitted, GrievanceState::Closed))->toBeFalse();
    expect($workflow->canTransition(GrievanceState::Closed, GrievanceState::InProgress))->toBeFalse();
    expect($workflow->canTransition(GrievanceState::Rejected, GrievanceState::UnderReview))->toBeFalse();
    expect($workflow->canTransition(GrievanceState::Submitted, GrievanceState::InProgress))->toBeFalse();
    // Under_review → InProgress is no longer direct.
    expect($workflow->canTransition(GrievanceState::UnderReview, GrievanceState::InProgress))->toBeFalse();
});

it('applies a transition, writes history, and fires event', function (): void {
    Event::fake([GrievanceStateChanged::class]);

    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->create();
    $actor = User::factory()->create();

    app(GrievanceWorkflow::class)->transition(
        $grievance,
        GrievanceState::UnderReview,
        $actor,
        'Starting review',
    );

    $grievance->refresh();
    expect($grievance->state)->toBe(GrievanceState::UnderReview);
    expect($grievance->statusHistory)->toHaveCount(1);

    $history = $grievance->statusHistory->first();
    expect($history->from_state)->toBe(GrievanceState::Submitted);
    expect($history->to_state)->toBe(GrievanceState::UnderReview);
    expect($history->actor_id)->toBe($actor->id);
    expect($history->note)->toBe('Starting review');

    Event::assertDispatched(GrievanceStateChanged::class);
});

it('throws InvalidTransition for a disallowed move', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->create();

    expect(fn () => app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::Closed))
        ->toThrow(InvalidTransition::class);
});

it('sets accepted_at when accepting from under_review', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::UnderReview)->create();
    $actor = User::factory()->create();

    app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::Accepted, $actor);

    expect($grievance->fresh()->accepted_at)->not->toBeNull();
    expect($grievance->fresh()->reviewed_by_id)->toBe($actor->id);
});

it('sets reviewed_at when rejecting from under_review', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::UnderReview)->create();
    $actor = User::factory()->create();

    app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::Rejected, $actor, 'Out of scope');

    expect($grievance->fresh()->reviewed_at)->not->toBeNull();
    expect($grievance->fresh()->review_comment)->toBe('Out of scope');
});

it('sets closed_at when closing from under_admin_review', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::UnderAdminReview)->create();

    app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::Closed);

    expect($grievance->fresh()->closed_at)->not->toBeNull();
});

it('is a no-op when target state equals current state', function (): void {
    Event::fake([GrievanceStateChanged::class]);
    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->create();

    app(GrievanceWorkflow::class)->transition($grievance, GrievanceState::Submitted);

    expect($grievance->statusHistory)->toHaveCount(0);
    Event::assertNotDispatched(GrievanceStateChanged::class);
});
