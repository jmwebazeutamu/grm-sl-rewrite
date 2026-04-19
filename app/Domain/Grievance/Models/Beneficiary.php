<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Beneficiary extends Model
{
    use HasFactory;

    protected $table = 'grievance_beneficiary';

    protected $fillable = [
        'grievance_id', 'name', 'gender', 'phone_number',
        'household_id', 'implementing_agency_id', 'social_programme_id',
    ];

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function implementingAgency(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'implementing_agency_id');
    }

    public function socialProgramme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'social_programme_id');
    }
}
