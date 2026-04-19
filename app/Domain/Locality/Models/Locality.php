<?php

declare(strict_types=1);

namespace App\Domain\Locality\Models;

use Database\Factories\LocalityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property int $section_id
 */
class Locality extends Model
{
    use HasFactory;

    protected $table = 'locality';

    protected $fillable = ['name', 'section_id'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    protected static function newFactory(): LocalityFactory
    {
        return LocalityFactory::new();
    }
}
