<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Single entry point for writing audit log rows. Intentionally thin — the
 * goal is that any listener in any domain can log in one line without
 * pulling in helpers or building payload shapes.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        ?array $payload = null,
    ): AuditEntry {
        return AuditEntry::create([
            'actor_id' => $actor?->id ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload,
            'ip_address' => request()?->ip(),
            'occurred_at' => now(),
        ]);
    }
}
