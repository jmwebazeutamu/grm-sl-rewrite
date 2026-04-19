<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

class FeedbackStatusPolicy extends ReferencePolicy
{
    protected function prefix(): string
    {
        return 'feedback_status';
    }
}
