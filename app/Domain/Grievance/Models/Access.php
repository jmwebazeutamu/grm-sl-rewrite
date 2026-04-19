<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Support\Concerns\RecordsAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'access';

    protected $fillable = ['name'];
}
