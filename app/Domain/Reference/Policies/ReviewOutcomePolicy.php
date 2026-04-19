<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class ReviewOutcomePolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'review_outcome';
    }
}
