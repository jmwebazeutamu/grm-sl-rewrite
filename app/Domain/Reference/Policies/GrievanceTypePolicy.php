<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class GrievanceTypePolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'grievance_type';
    }
}
