<?php

declare(strict_types=1);

namespace App\Domain\Locality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $table = 'section';

    protected $fillable = ['name', 'chiefdom_id'];

    public function chiefdom(): BelongsTo
    {
        return $this->belongsTo(Chiefdom::class);
    }

    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }
}
