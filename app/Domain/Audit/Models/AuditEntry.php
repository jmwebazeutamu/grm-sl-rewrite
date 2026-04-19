<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property ?int $actor_id
 * @property string $action
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?array<string, mixed> $payload
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class AuditEntry extends Model
{
    protected $table = 'audit_log';

    protected $fillable = [
        'actor_id', 'action', 'subject_type', 'subject_id',
        'payload', 'ip_address', 'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOccurredAfter($query, string $date): void
    {
        $query->where('occurred_at', '>=', $date);
    }

    public function scopeOccurredBefore($query, string $date): void
    {
        $query->where('occurred_at', '<=', $date);
    }
}
