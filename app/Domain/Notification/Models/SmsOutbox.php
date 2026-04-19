<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Model;

class SmsOutbox extends Model
{
    protected $table = 'sms_outbox';

    protected $fillable = [
        'to', 'body', 'provider_message_id',
        'status', 'failure_reason', 'dispatched_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime'];
    }
}
