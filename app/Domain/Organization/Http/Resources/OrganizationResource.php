<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Resources;

use App\Domain\Organization\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'acronym' => $this->acronym,
            'description' => $this->description,
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),
            'grievance_types' => $this->whenLoaded(
                'grievanceTypes',
                fn () => $this->grievanceTypes->map(fn ($gt) => ['id' => $gt->id, 'name' => $gt->name]),
            ),
            'office_count' => $this->when(isset($this->offices_count), fn () => $this->offices_count),
            'employee_count' => $this->when(isset($this->employees_count), fn () => $this->employees_count),
        ];
    }
}
