<?php

declare(strict_types=1);

namespace App\Domain\Reference\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrievanceType extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'grievance_type';

    protected $fillable = ['name'];

    public function caseConcepts(): HasMany
    {
        return $this->hasMany(CaseConcept::class);
    }
}
