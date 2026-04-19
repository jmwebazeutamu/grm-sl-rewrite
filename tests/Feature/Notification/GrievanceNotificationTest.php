<?php

declare(strict_types=1);

use App\Domain\Grievance\Actions\IssueFeedbackToken;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\FeedbackRequested;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Events\GrievanceSubmitted;
use App\Domain\Grievance\Listeners\SendFeedbackInvitation;
use App\Domain\Grievance\Listeners\SendGrievanceReceivedNotification;
use App\Domain\Grievance\Listeners\SendStateChangeNotification;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Notifications\FeedbackInvitationNotification;
use App\Domain\Grievance\Notifications\GrievanceReceivedNotification;
use App\Domain\Grievance\Notifications\GrievanceStateChangedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('sends a received notification via mail and sms on submission', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->create(['is_anonymous' => false]);
    $grievance->complainer()->create([
        'first_name' => 'Mariatu',
        'last_name' => 'Kargbo',
        'email' => 'mariatu@example.sl',
        'phone_number' => '+23276000000',
    ]);

    (new SendGrievanceReceivedNotification)->handle(new GrievanceSubmitted($grievance));

    Notification::assertSentOnDemand(GrievanceReceivedNotification::class, function ($notification, $channels, $notifiable) {
        expect($notifiable)->toBeInstanceOf(AnonymousNotifiable::class);
        expect($channels)->toContain('mail')->toContain('sms');

        return true;
    });
});

it('does not notify on anonymous submissions', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Submitted)->anonymous()->create();

    (new SendGrievanceReceivedNotification)->handle(new GrievanceSubmitted($grievance));

    Notification::assertNothingSent();
});

it('sends state-change notifications for meaningful states but skips Resolved', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::UnderReview)->create(['is_anonymous' => false]);
    $grievance->complainer()->create([
        'first_name' => 'A', 'last_name' => 'B',
        'email' => 'a@b.sl',
    ]);

    (new SendStateChangeNotification)->handle(
        new GrievanceStateChanged($grievance, GrievanceState::Submitted, GrievanceState::UnderReview),
    );
    Notification::assertSentOnDemand(GrievanceStateChangedNotification::class);
});

it('silences state-change notification on Resolved (feedback invitation covers it)', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create(['is_anonymous' => false]);
    $grievance->complainer()->create([
        'first_name' => 'A', 'last_name' => 'B',
        'email' => 'a@b.sl',
    ]);

    (new SendStateChangeNotification)->handle(
        new GrievanceStateChanged($grievance, GrievanceState::InProgress, GrievanceState::Resolved),
    );

    Notification::assertNothingSent();
});

it('sends the feedback invitation when a token is issued', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::Resolved)->create(['is_anonymous' => false]);
    $grievance->complainer()->create([
        'first_name' => 'A', 'last_name' => 'B',
        'email' => 'a@b.sl',
        'phone_number' => '+23276000001',
    ]);

    $token = app(IssueFeedbackToken::class)($grievance);

    (new SendFeedbackInvitation)->handle(new FeedbackRequested($grievance, $token));

    Notification::assertSentOnDemand(FeedbackInvitationNotification::class);
});
