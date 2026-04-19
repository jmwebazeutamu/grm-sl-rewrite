<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Enums;

enum GrievanceCategory: string
{
    case Corruption = 'corruption';
    case Administrative = 'administrative';

    public function label(): string
    {
        return match ($this) {
            self::Corruption => 'Corruption',
            self::Administrative => 'Administrative',
        };
    }
}
