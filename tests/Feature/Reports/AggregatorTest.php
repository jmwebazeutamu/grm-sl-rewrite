<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Reporting\Services\ReportAggregator;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('counts grievances by state', function (): void {
    Grievance::factory()->inState(GrievanceState::Submitted)->count(2)->create();
    Grievance::factory()->inState(GrievanceState::InProgress)->count(3)->create();
    Grievance::factory()->inState(GrievanceState::Closed)->count(1)->create();

    $counts = app(ReportAggregator::class)->stateCounts();

    expect($counts['submitted'])->toBe(2);
    expect($counts['in_progress'])->toBe(3);
    expect($counts['closed'])->toBe(1);
    expect($counts['rejected'])->toBe(0);
});

it('computes submissions per day over a window', function (): void {
    Grievance::factory()->create(['received_at' => now()->subDays(2)]);
    Grievance::factory()->create(['received_at' => now()->subDays(2)]);
    Grievance::factory()->create(['received_at' => now()]);

    $rows = app(ReportAggregator::class)->submissionsPerDay(5);

    expect($rows)->toHaveCount(5);
    expect(array_sum(array_column($rows, 'count')))->toBe(3);
});

it('invalidates cache on flush()', function (): void {
    Grievance::factory()->inState(GrievanceState::Submitted)->count(2)->create();

    $reports = app(ReportAggregator::class);
    expect($reports->stateCounts()['submitted'])->toBe(2);

    Grievance::factory()->inState(GrievanceState::Submitted)->create();

    expect($reports->stateCounts()['submitted'])->toBe(2); // still cached
    $reports->flush();
    expect($reports->stateCounts()['submitted'])->toBe(3);
});
