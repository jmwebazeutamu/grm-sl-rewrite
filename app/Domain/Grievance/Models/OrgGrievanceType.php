<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgGrievanceType extends Model
{
    use HasFactory;

    protected $table = 'org_grievance_types';

    protected $fillable = ['organization_id', 'label', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
