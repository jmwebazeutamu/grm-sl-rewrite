<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suspect extends Model
{
    use HasFactory;

    protected $table = 'grievance_suspect';

    protected $fillable = [
        'grievance_id',
        'first_name', 'last_name', 'title', 'gender',
        'email', 'phone_number', 'address',
        'organization_id', 'other_organization',
        'region_id', 'district_id', 'chiefdom_id', 'section_id', 'locality_id',
        'is_beneficiary', 'programme_id', 'implementing_organization_id',
        'beneficiary_id_number',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_beneficiary' => 'boolean',
        ];
    }

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function implementingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'implementing_organization_id');
    }
}
