<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Suspect;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BeneficiaryController extends Controller
{
    public function index(Request $request): Response
    {
        // grievance.programme / implementingOrganization load the grievance-level
        // fallback for listings where the beneficiary row itself has nulls
        // (most legacy-imported beneficiaries do).
        $query = Suspect::query()
            ->where('grievance_suspect.is_beneficiary', true)
            ->with([
                'programme:id,name',
                'implementingOrganization:id,name',
                'grievance:id,programme_id,implementing_organization_id',
                'grievance.programme:id,name',
                'grievance.implementingOrganization:id,name',
            ])
            ->join('grievance', 'grievance.id', '=', 'grievance_suspect.grievance_id')
            ->select([
                'grievance_suspect.*',
                'grievance.state',
                'grievance.g_number',
                'grievance.created_at as grievance_created_at',
                'grievance.classified_organization_id',
            ])
            ->whereNull('grievance.deleted_at');

        $user = $request->user();
        if (! $user->hasAnyRole(['super-admin', 'acc-reviewer', 'grm-data-operator']) && $user->organization_id !== null) {
            $query->where('grievance.classified_organization_id', $user->organization_id);
        }

        $query
            ->when($request->input('search'), fn ($q, $v) => $q->where(
                fn ($inner) => $inner->where('grievance_suspect.first_name', 'like', "%{$v}%")
                    ->orWhere('grievance_suspect.last_name', 'like', "%{$v}%")
                    ->orWhere('grievance_suspect.beneficiary_id_number', 'like', "%{$v}%"),
            ))
            ->when($request->input('programme_id'), fn ($q, $v) => $q->where('grievance_suspect.programme_id', $v))
            ->when($request->input('organization_id'), fn ($q, $v) => $q->where('grievance_suspect.implementing_organization_id', $v))
            ->when($request->input('state'), fn ($q, $v) => $q->where('grievance.state', $v))
            ->when($request->input('date_from'), fn ($q, $v) => $q->whereDate('grievance_suspect.created_at', '>=', $v))
            ->when($request->input('date_to'), fn ($q, $v) => $q->whereDate('grievance_suspect.created_at', '<=', $v));

        $beneficiaries = $query
            ->orderByDesc('grievance_suspect.created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Grievance/Admin/Beneficiaries', [
            'beneficiaries' => $beneficiaries,
            'programmes' => Programme::orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'states' => collect(GrievanceState::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters' => $request->only(['search', 'programme_id', 'organization_id', 'state', 'date_from', 'date_to']),
        ]);
    }
}
