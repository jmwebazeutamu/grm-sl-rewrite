<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Listeners;

use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Reporting\Services\QuarterlyReportAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvalidateQuarterlyCache implements ShouldQueue
{
    public function __construct(private readonly QuarterlyReportAggregator $aggregator)
    {
    }

    public function handle(GrievanceStateChanged $event): void
    {
        $g = $event->grievance;
        $year = $g->created_at ? (int) $g->created_at->year : (int) now()->year;

        $this->aggregator->flush($year, $g->classified_organization_id);
        $this->aggregator->flush($year, null);
    }
}
