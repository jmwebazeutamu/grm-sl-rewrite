<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Notification\Models\NotificationPreference;

use function Pest\Laravel\actingAs;

it('shows current preferences with all-on defaults', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('settings.notifications.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Notifications')
            ->where('preferences.email_enabled', true)
            ->where('preferences.sms_enabled', true)
            ->where('preferences.in_app_enabled', true));
});

it('persists updated preferences', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->put(route('settings.notifications.update'), [
            'email_enabled' => true,
            'sms_enabled' => false,
            'in_app_enabled' => true,
        ])
        ->assertRedirect();

    $prefs = NotificationPreference::where('person_id', $user->id)->first();
    expect($prefs->sms_enabled)->toBeFalse();
    expect($prefs->email_enabled)->toBeTrue();
});

it('returns only enabled channels', function (): void {
    $prefs = new NotificationPreference([
        'email_enabled' => true, 'sms_enabled' => false, 'in_app_enabled' => true,
    ]);

    expect($prefs->enabledChannels())->toBe(['database', 'mail']);
});

it('returns default channels when user has no preference row', function (): void {
    $user = User::factory()->create();

    expect($user->preferredChannels())->toBe(['database', 'mail', 'sms']);
});
