<?php

declare(strict_types=1);

use App\Domain\Audit\Listeners\LogGrievanceStateChanged;
use App\Domain\Audit\Listeners\LogGrievanceSubmitted;
use App\Domain\Audit\Listeners\LogUserRoleAssigned;
use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Events\GrievanceSubmitted;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Models\User;

it('writes a row on grievance submitted', function (): void {
    $grievance = Grievance::factory()->create();

    (new LogGrievanceSubmitted(app(AuditLogger::class)))->handle(new GrievanceSubmitted($grievance));

    $entry = AuditEntry::where('action', 'grievance.submitted')->first();
    expect($entry)->not->toBeNull();
    expect($entry->subject_id)->toBe($grievance->id);
    expect($entry->payload['g_number'])->toBe($grievance->g_number);
});

it('captures from/to in grievance state_changed rows', function (): void {
    $grievance = Grievance::factory()->inState(GrievanceState::UnderReview)->create();

    (new LogGrievanceStateChanged(app(AuditLogger::class)))->handle(
        new GrievanceStateChanged($grievance, GrievanceState::Submitted, GrievanceState::UnderReview, 'Picking up'),
    );

    $entry = AuditEntry::where('action', 'grievance.state_changed')->first();
    expect($entry->payload['from'])->toBe('submitted');
    expect($entry->payload['to'])->toBe('under_review');
    expect($entry->payload['note'])->toBe('Picking up');
});

it('captures assigned roles with actor', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    (new LogUserRoleAssigned(app(AuditLogger::class)))->handle(
        new UserRoleAssigned($target, ['grm-officer'], $actor),
    );

    $entry = AuditEntry::where('action', 'user.roles_assigned')->first();
    expect($entry->actor_id)->toBe($actor->id);
    expect($entry->subject_id)->toBe($target->id);
    expect($entry->payload['roles'])->toBe(['grm-officer']);
});
