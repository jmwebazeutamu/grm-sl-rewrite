<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\Area;
use App\Domain\Reference\Models\CaseConcept;
use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Classification extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'grievance_classification';

    protected $fillable = [
        'grievance_id', 'organization_id', 'programme_id',
        'case_concept_id', 'responsible_area_id', 'access_id',
    ];

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

    public function caseConcept(): BelongsTo
    {
        return $this->belongsTo(CaseConcept::class);
    }

    public function responsibleArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'responsible_area_id');
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(Access::class);
    }
}
