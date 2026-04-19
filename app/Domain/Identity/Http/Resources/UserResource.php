<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Resources;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'position' => $this->position,
            'organization_id' => $this->organization_id ?? null,
            'office_id' => $this->office_id ?? null,
            'is_active' => $this->is_active ?? true,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'organization' => $this->whenLoaded('organization', fn () => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'acronym' => $this->organization->getAttribute('acronym'),
            ] : null),
            'office' => $this->whenLoaded('office', fn () => $this->office ? [
                'id' => $this->office->id,
                'name' => $this->office->name,
            ] : null),
            'email_verified' => $this->email_verified_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
