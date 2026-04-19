<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Organization\Enums\ProgrammeStatus;
use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Programme extends Model
{
    use HasFactory;
    use RecordsAuthorship;
    use SoftDeletes;

    protected $table = 'programme';

    protected $fillable = [
        'name', 'acronym', 'code',
        'organization_id', 'status', 'active', 'sla_days',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'status' => ProgrammeStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProgrammeStatus::Active->value);
    }
}
