<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $table = 'notification_preference';

    protected $fillable = [
        'person_id',
        'email_enabled', 'sms_enabled', 'in_app_enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_id');
    }

    /**
     * Channel names Laravel's notification system uses to route delivery.
     *
     * @return list<string>
     */
    public function enabledChannels(): array
    {
        $channels = [];
        if ($this->in_app_enabled) {
            $channels[] = 'database';
        }
        if ($this->email_enabled) {
            $channels[] = 'mail';
        }
        if ($this->sms_enabled) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public static function defaultChannels(): array
    {
        return ['database', 'mail', 'sms'];
    }
}
