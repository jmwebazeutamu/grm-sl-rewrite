<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Services;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use Illuminate\Support\Collection;

/**
 * Merges status transitions + actions + feedback into one chronological
 * stream. Replaces the legacy pattern of showing each in a separate tab.
 */
class GrievanceTimeline
{
    /**
     * @return Collection<int, array{
     *   kind: 'submitted'|'state'|'action'|'feedback',
     *   occurred_at: \Illuminate\Support\Carbon,
     *   actor: ?array{id: int, name: string},
     *   data: array<string, mixed>
     * }>
     */
    public function build(Grievance $grievance): Collection
    {
        $grievance->loadMissing([
            'statusHistory.actor:id,name',
            'actions.createdBy:id,name',
            'actions.assignedTo:id,name',
            'feedback',
        ]);

        $items = collect();

        $history = $grievance->statusHistory->values();
        $skipNext = false;

        foreach ($history as $i => $entry) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            // Merge `escalated → reopened` into one combined timeline entry.
            // When an `escalated` transition is immediately followed by a
            // `reopened` transition by the same actor, render a single row
            // with the escalation's note (closure comment) and skip the pair.
            $next = $history[$i + 1] ?? null;
            if (
                $entry->to_state === GrievanceState::Escalated
                && $next !== null
                && $next->to_state === GrievanceState::Reopened
            ) {
                $items->push([
                    'kind' => 'state',
                    'occurred_at' => $next->occurred_at,
                    'actor' => $entry->actor ? ['id' => $entry->actor->id, 'name' => $entry->actor->name] : null,
                    'data' => [
                        'from' => $entry->from_state?->value,
                        'to' => GrievanceState::Reopened->value,
                        'to_label' => 'Escalated and reopened',
                        'note' => $entry->note,
                        'outcome' => 'dissatisfied',
                    ],
                ]);
                $skipNext = true;
                continue;
            }

            $items->push([
                'kind' => $entry->from_state === null ? 'submitted' : 'state',
                'occurred_at' => $entry->occurred_at,
                'actor' => $entry->actor ? ['id' => $entry->actor->id, 'name' => $entry->actor->name] : null,
                'data' => [
                    'from' => $entry->from_state?->value,
                    'to' => $entry->to_state->value,
                    'to_label' => $entry->to_state->label(),
                    'note' => $entry->note,
                    'outcome' => $entry->to_state === GrievanceState::Closed ? 'satisfied' : null,
                ],
            ]);
        }

        foreach ($grievance->actions as $action) {
            $items->push([
                'kind' => 'action',
                'occurred_at' => $action->created_at,
                'actor' => $action->createdBy ? ['id' => $action->createdBy->id, 'name' => $action->createdBy->name] : null,
                'data' => [
                    'type' => $action->type->value,
                    'type_label' => $action->type->label(),
                    'icon' => $action->type->icon(),
                    'body' => $action->body,
                    'assigned_to' => $action->assignedTo?->name,
                ],
            ]);
        }

        if ($grievance->feedback) {
            $items->push([
                'kind' => 'feedback',
                'occurred_at' => $grievance->feedback->submitted_at,
                'actor' => null, // complainant, intentionally anonymous in timeline
                'data' => [
                    'rating' => $grievance->feedback->rating->value,
                    'rating_label' => $grievance->feedback->rating->label(),
                    'comment' => $grievance->feedback->comment,
                    'channel' => $grievance->feedback->channel,
                ],
            ]);
        }

        return $items->sortBy('occurred_at')->values();
    }
}
