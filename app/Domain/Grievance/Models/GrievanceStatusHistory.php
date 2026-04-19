<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrievanceStatusHistory extends Model
{
    protected $table = 'grievance_status_history';

    public $timestamps = false;

    protected $fillable = [
        'grievance_id', 'from_state', 'to_state', 'note',
        'actor_id', 'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_state' => GrievanceState::class,
            'to_state' => GrievanceState::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
