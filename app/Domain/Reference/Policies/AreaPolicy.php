<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class AreaPolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'area';
    }
}
