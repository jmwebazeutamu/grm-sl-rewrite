<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class ActionTypePolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'action_type';
    }
}
