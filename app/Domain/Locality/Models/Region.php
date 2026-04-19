<?php

declare(strict_types=1);

namespace App\Domain\Locality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $table = 'region';

    protected $fillable = ['name', 'country_id'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
