<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Reference\Models\GrievanceType;
use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory;
    use RecordsAuthorship;
    use SoftDeletes;

    protected $table = 'organization';

    protected $fillable = ['name', 'acronym', 'description', 'parent_id', 'sla_days'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function grievanceTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            GrievanceType::class,
            'organization_grievance_type',
        )->withTimestamps();
    }
}
