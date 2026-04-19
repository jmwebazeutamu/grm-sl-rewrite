<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Enums;

enum AttachmentSource: string
{
    case Submission = 'submission';
    case Officer = 'officer';

    public function label(): string
    {
        return match ($this) {
            self::Submission => 'Submitted with grievance',
            self::Officer => 'Officer upload',
        };
    }
}
