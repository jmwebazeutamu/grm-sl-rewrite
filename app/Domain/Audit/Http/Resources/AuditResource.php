<?php

declare(strict_types=1);

namespace App\Domain\Audit\Http\Resources;

use App\Domain\Audit\Models\AuditEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditEntry */
class AuditResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'payload' => $this->payload,
            'ip_address' => $this->ip_address,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
