<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class PriorityPolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'priority';
    }
}
