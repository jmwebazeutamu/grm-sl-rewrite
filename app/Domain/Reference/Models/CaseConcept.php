<?php

declare(strict_types=1);

namespace App\Domain\Reference\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseConcept extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'case_concept';

    protected $fillable = ['name', 'description', 'grievance_type_id'];

    public function grievanceType(): BelongsTo
    {
        return $this->belongsTo(GrievanceType::class);
    }
}
