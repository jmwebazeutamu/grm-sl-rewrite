<?php

declare(strict_types=1);

use App\Domain\Grievance\Actions\IssueFeedbackToken;
use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the feedback form for a valid token', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create();
    $token = app(IssueFeedbackToken::class)($grievance);

    get(route('grievances.feedback.show', $token->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Grievance/Feedback/Form')
            ->where('g_number', $grievance->g_number));
});

it('records a satisfied rating and leaves the case in resolved for admin closure', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create();
    $token = app(IssueFeedbackToken::class)($grievance);

    post(route('grievances.feedback.store', $token->token), [
        'rating' => FeedbackRating::VerySatisfied->value,
        'comment' => 'Handled quickly.',
    ])->assertRedirect(route('grievances.feedback.thanks'));

    // Feedback is recorded but closure is now gated by the admin closure review.
    expect($grievance->fresh()->state)->toBe(GrievanceState::Resolved);
    expect($token->fresh()->consumed_at)->not->toBeNull();
    expect($grievance->fresh()->feedback?->rating)->toBe(FeedbackRating::VerySatisfied);
});

it('records a dissatisfied rating without auto-escalating', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create();
    $token = app(IssueFeedbackToken::class)($grievance);

    post(route('grievances.feedback.store', $token->token), [
        'rating' => FeedbackRating::Dissatisfied->value,
    ])->assertRedirect();

    // Escalation is now an explicit admin action, not a side-effect of feedback.
    expect($grievance->fresh()->state)->toBe(GrievanceState::Resolved);
    expect($grievance->fresh()->feedback?->rating)->toBe(FeedbackRating::Dissatisfied);
});

it('refuses a consumed token', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create();
    $token = app(IssueFeedbackToken::class)($grievance);
    $token->consume();

    get(route('grievances.feedback.show', $token->token))->assertStatus(410);
});

it('refuses an expired token', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create();
    $token = app(IssueFeedbackToken::class)($grievance);
    $token->update(['expires_at' => now()->subDay()]);

    get(route('grievances.feedback.show', $token->token))->assertStatus(410);
});
