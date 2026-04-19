<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Region;
use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory;
    use RecordsAuthorship;
    use SoftDeletes;

    protected $table = 'office';

    protected $fillable = [
        'name', 'acronym', 'address', 'is_headquarters',
        'organization_id', 'region_id', 'district_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_headquarters' => 'boolean'];
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

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'office_person', 'office_id', 'person_id')
            ->withTimestamps();
    }
}
