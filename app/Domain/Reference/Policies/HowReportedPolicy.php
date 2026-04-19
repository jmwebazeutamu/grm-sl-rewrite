<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class HowReportedPolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'how_reported';
    }
}
