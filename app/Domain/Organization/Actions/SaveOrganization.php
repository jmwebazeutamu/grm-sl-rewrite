<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

class SaveOrganization
{
    /**
     * @param  array{name: string, acronym?: ?string, description?: ?string, parent_id?: ?int, grievance_type_ids?: array<int>}  $data
     */
    public function __invoke(array $data, ?Organization $organization = null): Organization
    {
        return DB::transaction(function () use ($data, $organization): Organization {
            $grievanceTypeIds = $data['grievance_type_ids'] ?? [];
            unset($data['grievance_type_ids']);

            $organization = $organization
                ? tap($organization)->update($data)
                : Organization::create($data);

            $organization->grievanceTypes()->sync($grievanceTypeIds);

            return $organization->refresh()->load('grievanceTypes');
        });
    }
}
