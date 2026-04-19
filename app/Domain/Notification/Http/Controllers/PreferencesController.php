<?php

declare(strict_types=1);

namespace App\Domain\Notification\Http\Controllers;

use App\Domain\Notification\Http\Requests\UpdatePreferencesRequest;
use App\Domain\Notification\Models\NotificationPreference;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        $prefs = NotificationPreference::firstOrCreate(
            ['person_id' => $request->user()->id],
        );

        return Inertia::render('Settings/Notifications', [
            'preferences' => [
                'email_enabled' => $prefs->email_enabled,
                'sms_enabled' => $prefs->sms_enabled,
                'in_app_enabled' => $prefs->in_app_enabled,
            ],
            'has_phone' => $request->user()->phone_number !== null,
        ]);
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        NotificationPreference::updateOrCreate(
            ['person_id' => $request->user()->id],
            $request->validated(),
        );

        return back()->with('success', 'Notification preferences updated.');
    }
}
