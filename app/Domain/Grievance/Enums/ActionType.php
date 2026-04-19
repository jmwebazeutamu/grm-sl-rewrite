<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Enums;

/**
 * The single action vocabulary replaces the legacy split between
 * `grievance_action_taken`, `grievance_remark`, and `grievance_resolution`.
 * An action's type tells you what role it played in the timeline.
 */
enum ActionType: string
{
    case Investigate = 'investigate';
    case Contact = 'contact';
    case Update = 'update';
    case Resolve = 'resolve';
    case Escalate = 'escalate';

    public function label(): string
    {
        return match ($this) {
            self::Investigate => 'Investigation',
            self::Contact => 'Contact',
            self::Update => 'Update',
            self::Resolve => 'Resolution',
            self::Escalate => 'Escalation',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Investigate => 'search',
            self::Contact => 'phone',
            self::Update => 'message-circle',
            self::Resolve => 'check-circle',
            self::Escalate => 'alert-triangle',
        };
    }
}
