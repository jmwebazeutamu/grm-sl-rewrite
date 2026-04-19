<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;
    use RecordsAuthorship;
    use SoftDeletes;

    protected $table = 'employee';

    protected $fillable = [
        'first_name', 'last_name', 'email',
        'mobile_number', 'office_number',
        'organization_id', 'office_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }
}
