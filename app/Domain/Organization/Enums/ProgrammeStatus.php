<?php

declare(strict_types=1);

namespace App\Domain\Organization\Enums;

enum ProgrammeStatus: string
{
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }
}
