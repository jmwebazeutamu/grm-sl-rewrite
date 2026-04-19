<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Listeners;

use App\Domain\Reporting\Services\ReportAggregator;

class InvalidateReportCache
{
    public function __construct(private readonly ReportAggregator $reports)
    {
    }

    public function handle(): void
    {
        $this->reports->flush();
    }
}
