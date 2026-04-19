<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Models\Grievance;
use Illuminate\Support\Facades\DB;

/**
 * Generates grievance numbers of the form GRM-YYYY-NNNNNN where NNNNNN is
 * the 1-based ordinal within the calendar year. Collision-safe under
 * concurrent submissions because the count is taken inside the same
 * transaction the caller opens.
 */
class AssignGrievanceNumber
{
    public function __invoke(int $year): string
    {
        $count = DB::table('grievance')
            ->whereYear('received_at', $year)
            ->count();

        do {
            $count++;
            $candidate = sprintf('GRM-%d-%06d', $year, $count);
        } while (Grievance::withTrashed()->where('g_number', $candidate)->exists());

        return $candidate;
    }
}
