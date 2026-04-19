<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Enums;

/**
 * Full categorization pipeline:
 *
 *   submitted → under_review → accepted → categorized → assigned
 *   → org_classified → in_progress → resolved → closed
 *
 * Escape valves: rejected (terminal from under_review), trashed (from
 * under_review or in_progress), escalated (from resolved, loops back
 * to in_progress).
 */
enum GrievanceState: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Categorized = 'categorized';
    case Assigned = 'assigned';
    case OrgClassified = 'org_classified';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Trashed = 'trashed';
    case Escalated = 'escalated';
    case UnderAdminReview = 'under_admin_review';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::Accepted => 'Accepted',
            self::Categorized => 'Categorized',
            self::Assigned => 'Assigned',
            self::OrgClassified => 'Org classified',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Rejected => 'Rejected',
            self::Trashed => 'Trashed',
            self::Escalated => 'Escalated',
            self::UnderAdminReview => 'Under admin review',
            self::Reopened => 'Reopened',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'sky',
            self::UnderReview => 'amber',
            self::Accepted => 'teal',
            self::Categorized => 'cyan',
            self::Assigned => 'violet',
            self::OrgClassified => 'purple',
            self::InProgress => 'indigo',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
            self::Rejected => 'rose',
            self::Trashed => 'zinc',
            self::Escalated => 'orange',
            self::UnderAdminReview => 'amber',
            self::Reopened => 'orange',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Trashed, self::Closed], true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /** @return list<self> */
    public static function activeStates(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->isActive()));
    }

    /**
     * States that precede in_progress — the "pipeline" where case work
     * cannot happen yet. Used by the UI to hide the ActionComposer.
     */
    public function isPipeline(): bool
    {
        return in_array($this, [
            self::Submitted, self::UnderReview, self::Accepted,
            self::Categorized, self::Assigned, self::OrgClassified,
        ], true);
    }
}
