<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Organization\Models\Organization;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $name
 * @property ?string $phone_number
 * @property ?string $position
 * @property ?int $organization_id
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    // Legacy table preserved from the GRM app.
    protected $table = 'person';

    protected $fillable = [
        'username',
        'email',
        'name',
        'password',
        'phone_number',
        'position',
        'organization_id',
        'office_id',
        'is_active',
        'expo_push_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class, 'person_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Organization\Models\Office::class);
    }

    /** Back-compat alias during the rename transition. */
    public function primaryOrganization(): BelongsTo
    {
        return $this->organization();
    }

    /** Back-compat shim for any callers still reading the old attribute name. */
    public function getPrimaryOrganizationIdAttribute(): ?int
    {
        return $this->organization_id;
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone_number;
    }

    public function routeNotificationForExpo(): ?string
    {
        return $this->getAttribute('expo_push_token');
    }

    /**
     * @return list<string>
     */
    public function preferredChannels(): array
    {
        return $this->notificationPreference?->enabledChannels()
            ?? NotificationPreference::defaultChannels();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
