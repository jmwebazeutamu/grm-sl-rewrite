<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers;

use App\Domain\Grievance\Actions\SubmitGrievance;
use App\Domain\Grievance\Http\Requests\SubmitGrievanceRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Locality\Models\Region;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicGrievanceController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Grievance/Public/Submit', [
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
            'howReported' => HowReported::orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'programmes' => Programme::active()->orderBy('name')->get(['id', 'name', 'acronym', 'organization_id']),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
        ]);
    }

    public function store(SubmitGrievanceRequest $request, SubmitGrievance $submit): RedirectResponse
    {
        $data = $request->validated();
        unset($data['recaptcha_token'], $data['attachments']);

        $grievance = $submit($data, $request->file('attachments') ?? []);

        return redirect()
            ->route('grievances.public.confirmation', ['g_number' => $grievance->g_number])
            ->with('success', 'Your grievance has been submitted.');
    }

    public function confirmation(string $gNumber): Response
    {
        $grievance = Grievance::where('g_number', $gNumber)->firstOrFail();

        return Inertia::render('Grievance/Public/Confirmation', [
            'g_number' => $grievance->g_number,
            'state_label' => $grievance->state->label(),
            'received_at' => $grievance->received_at->toIso8601String(),
        ]);
    }

    public function status(string $gNumber): Response
    {
        $grievance = Grievance::where('g_number', $gNumber)->firstOrFail();

        return Inertia::render('Grievance/Public/Status', [
            'g_number' => $grievance->g_number,
            'state_label' => $grievance->state->label(),
            'received_at' => $grievance->received_at->toIso8601String(),
            'resolved_at' => $grievance->resolved_at?->toIso8601String(),
            'closed_at' => $grievance->closed_at?->toIso8601String(),
        ]);
    }

    public function statusLookup(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $grmNumber = $request->input('grm_number', $request->query('grm_number', ''));
        $grievance = Grievance::with('classifiedOrganization:id,name')
            ->where('g_number', $grmNumber)
            ->first();

        if (! $grievance) {
            return response()->json([
                'error' => "No grievance found with reference '{$grmNumber}'.",
            ]);
        }

        return response()->json([
            'grm_number' => $grievance->g_number,
            'submitted_at' => $grievance->created_at?->format('d M Y'),
            'last_updated' => $grievance->updated_at?->format('d M Y'),
            'state' => $grievance->state->label(),
            'state_value' => $grievance->state->value,
            'assigned_org' => $grievance->classifiedOrganization?->name ?? '—',
        ]);
    }
}
