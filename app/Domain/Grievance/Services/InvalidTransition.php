<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Services;

use App\Domain\Grievance\Enums\GrievanceState;
use RuntimeException;

class InvalidTransition extends RuntimeException
{
    public function __construct(GrievanceState $from, GrievanceState $to)
    {
        parent::__construct("Cannot transition grievance from {$from->value} to {$to->value}.");
    }
}
