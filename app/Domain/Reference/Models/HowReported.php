<?php

declare(strict_types=1);

namespace App\Domain\Reference\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HowReported extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'how_reported';

    protected $fillable = ['name'];
}
