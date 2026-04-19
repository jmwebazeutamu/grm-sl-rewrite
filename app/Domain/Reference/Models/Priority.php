<?php

declare(strict_types=1);

namespace App\Domain\Reference\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'priority';

    protected $fillable = [
        'name', 'description', 'ranking',
        'response_time_hours', 'resolution_time_hours',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ranking' => 'integer',
            'response_time_hours' => 'integer',
            'resolution_time_hours' => 'integer',
        ];
    }
}
