<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Events;

use App\Domain\Grievance\Models\Grievance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrievanceSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Grievance $grievance)
    {
    }
}
