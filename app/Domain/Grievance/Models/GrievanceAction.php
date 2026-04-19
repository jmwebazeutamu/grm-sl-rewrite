<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\RecordsAuthorship;
use Database\Factories\GrievanceActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property ActionType $type
 * @property string $body
 */
class GrievanceAction extends Model
{
    use HasFactory;
    use RecordsAuthorship;

    protected $table = 'grievance_action';

    protected $fillable = ['grievance_id', 'type', 'body', 'assigned_to_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => ActionType::class];
    }

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    protected static function newFactory(): GrievanceActionFactory
    {
        return GrievanceActionFactory::new();
    }
}
