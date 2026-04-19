<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Controllers\Admin;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlaSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor->hasAnyRole(['org-admin', 'grm-officer', 'super-admin']), 403);

        $org = Organization::findOrFail($actor->organization_id);

        $programmes = Programme::where('organization_id', $org->id)
            ->orderBy('name')
            ->get(['id', 'name', 'sla_days'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sla_days' => $p->sla_days,
            ]);

        return Inertia::render('OrgSettings/SlaSettings/Index', [
            'org_sla_days' => (int) ($org->sla_days ?? 30),
            'programmes' => $programmes,
        ]);
    }

    public function updateOrg(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->hasAnyRole(['org-admin', 'grm-officer', 'super-admin']), 403);

        $data = $request->validate([
            'sla_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $org = Organization::findOrFail($actor->organization_id);
        $org->update(['sla_days' => $data['sla_days']]);

        return back()->with('success', 'Organisation SLA updated.');
    }

    public function updateProgramme(Request $request, Programme $programme): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->hasAnyRole(['org-admin', 'grm-officer', 'super-admin']), 403);
        abort_unless(
            $actor->hasRole('super-admin') || $actor->organization_id === $programme->organization_id,
            403,
        );

        $data = $request->validate([
            'sla_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $programme->update(['sla_days' => $data['sla_days']]);

        return back()->with('success', 'Programme SLA updated.');
    }
}
