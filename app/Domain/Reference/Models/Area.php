<?php

declare(strict_types=1);

namespace App\Domain\Reference\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'area';

    protected $fillable = ['name'];
}
