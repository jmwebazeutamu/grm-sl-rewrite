<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    protected $table = 'saved_reports';

    protected $fillable = ['name', 'created_by_id', 'organization_id', 'fields', 'filters'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'filters' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
