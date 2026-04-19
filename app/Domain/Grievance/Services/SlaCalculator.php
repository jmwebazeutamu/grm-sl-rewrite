<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Services;

use App\Domain\Grievance\Models\Grievance;

class SlaCalculator
{
    private const GLOBAL_DEFAULT = 30;

    private const TERMINAL_STATES = [
        'resolved', 'under_admin_review', 'closed', 'rejected', 'trashed',
    ];

    /**
     * @return array{days_open: int, sla_days: int, status: string}
     */
    public function calculate(Grievance $grievance): array
    {
        $slaDays = $grievance->programme?->sla_days
            ?? $grievance->classifiedOrganization?->sla_days
            ?? $grievance->implementingOrganization?->sla_days
            ?? self::GLOBAL_DEFAULT;

        $end = $grievance->resolved_at
            ?? $grievance->closed_at
            ?? now();

        $daysOpen = $grievance->created_at
            ? (int) ceil($grievance->created_at->diffInDays($end))
            : 0;

        if (in_array($grievance->state->value, self::TERMINAL_STATES, true)) {
            $status = 'grey';
        } elseif ($daysOpen >= $slaDays) {
            $status = 'red';
        } elseif ($daysOpen >= (int) ($slaDays * 0.8)) {
            $status = 'amber';
        } else {
            $status = 'green';
        }

        return [
            'days_open' => $daysOpen,
            'sla_days' => (int) $slaDays,
            'status' => $status,
        ];
    }
}
