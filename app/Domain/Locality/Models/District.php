<?php

declare(strict_types=1);

namespace App\Domain\Locality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory;

    protected $table = 'district';

    protected $fillable = ['name', 'region_id'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function chiefdoms(): HasMany
    {
        return $this->hasMany(Chiefdom::class);
    }
}
