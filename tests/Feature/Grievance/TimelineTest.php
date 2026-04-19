<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAction;
use App\Domain\Grievance\Models\GrievanceFeedback;
use App\Domain\Grievance\Models\GrievanceStatusHistory;
use App\Domain\Grievance\Services\GrievanceTimeline;

it('merges status history, actions, and feedback in chronological order', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Closed)->create([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    GrievanceStatusHistory::create([
        'grievance_id' => $grievance->id,
        'from_state' => null,
        'to_state' => GrievanceState::Submitted->value,
        'occurred_at' => now()->subDays(5),
    ]);
    GrievanceStatusHistory::create([
        'grievance_id' => $grievance->id,
        'from_state' => GrievanceState::Submitted->value,
        'to_state' => GrievanceState::UnderReview->value,
        'occurred_at' => now()->subDays(4),
    ]);
    GrievanceAction::factory()->for($grievance)->create([
        'type' => ActionType::Investigate,
        'body' => 'Spoke to district officer.',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);
    GrievanceFeedback::factory()->for($grievance)->create([
        'rating' => FeedbackRating::Satisfied,
        'submitted_at' => now()->subDay(),
    ]);

    $items = app(GrievanceTimeline::class)->build($grievance);

    expect($items)->toHaveCount(4);
    expect($items[0]['kind'])->toBe('submitted');
    expect($items[1]['kind'])->toBe('state');
    expect($items[2]['kind'])->toBe('action');
    expect($items[3]['kind'])->toBe('feedback');
});
