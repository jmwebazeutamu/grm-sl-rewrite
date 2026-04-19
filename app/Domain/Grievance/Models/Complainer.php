<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complainer extends Model
{
    use HasFactory;

    protected $table = 'grievance_complainer';

    protected $fillable = [
        'grievance_id',
        'first_name', 'last_name', 'gender',
        'email', 'phone_number', 'address',
        'organization_id', 'other_organization',
        'region_id', 'district_id', 'chiefdom_id', 'section_id', 'locality_id',
    ];

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function chiefdom(): BelongsTo
    {
        return $this->belongsTo(Chiefdom::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }
}
