<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class StatusPolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'status';
    }
}
