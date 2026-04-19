<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Enums;

/**
 * Five-point complainant satisfaction scale. `Dissatisfied` or worse
 * triggers automatic escalation in RecordFeedback.
 */
enum FeedbackRating: int
{
    case VeryDissatisfied = 1;
    case Dissatisfied = 2;
    case Neutral = 3;
    case Satisfied = 4;
    case VerySatisfied = 5;

    public function label(): string
    {
        return match ($this) {
            self::VeryDissatisfied => 'Very dissatisfied',
            self::Dissatisfied => 'Dissatisfied',
            self::Neutral => 'Neutral',
            self::Satisfied => 'Satisfied',
            self::VerySatisfied => 'Very satisfied',
        };
    }

    public function triggersEscalation(): bool
    {
        return $this->value <= self::Dissatisfied->value;
    }
}
