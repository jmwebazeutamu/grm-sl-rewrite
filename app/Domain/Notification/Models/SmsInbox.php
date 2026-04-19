<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\Grievance\Models\Grievance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsInbox extends Model
{
    protected $table = 'sms_inbox';

    protected $fillable = [
        'from', 'body', 'provider_message_id',
        'parsed_command', 'matched_grievance_id', 'received_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function matchedGrievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class, 'matched_grievance_id');
    }
}
