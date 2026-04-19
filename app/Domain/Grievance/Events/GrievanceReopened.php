<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Events;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrievanceReopened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Grievance $grievance,
        public readonly User $reviewer,
        public readonly string $closureComment,
    ) {
    }
}
