<?php

declare(strict_types=1);

namespace App\Domain\Locality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chiefdom extends Model
{
    use HasFactory;

    protected $table = 'chiefdom';

    protected $fillable = ['name', 'district_id'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
