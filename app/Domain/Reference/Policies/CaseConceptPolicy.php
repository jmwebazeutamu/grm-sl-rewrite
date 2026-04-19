<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class CaseConceptPolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'case_concept';
    }
}
