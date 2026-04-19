<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Events;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrievanceStateChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Grievance $grievance,
        public readonly ?GrievanceState $from,
        public readonly GrievanceState $to,
        public readonly ?string $note = null,
    ) {
    }
}
