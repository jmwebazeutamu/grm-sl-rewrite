<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrievanceFeedbackToken extends Model
{
    protected $table = 'grievance_feedback_token';

    protected $fillable = ['grievance_id', 'token', 'expires_at', 'consumed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    public function consume(): void
    {
        $this->update(['consumed_at' => now()]);
    }
}
