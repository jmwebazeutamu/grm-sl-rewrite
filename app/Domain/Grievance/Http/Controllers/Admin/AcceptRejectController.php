<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\AcceptGrievance;
use App\Domain\Grievance\Actions\RejectGrievance;
use App\Domain\Grievance\Http\Requests\AcceptRejectRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AcceptRejectController extends Controller
{
    public function accept(Grievance $grievance, AcceptGrievance $accept): RedirectResponse
    {
        $this->authorize('review', $grievance);

        $accept($grievance, request()->user());

        return back()->with('success', 'Grievance accepted. Proceed to categorization.');
    }

    public function reject(
        AcceptRejectRequest $request,
        Grievance $grievance,
        RejectGrievance $reject,
    ): RedirectResponse {
        $this->authorize('review', $grievance);

        $reason = $request->validated()['reason'] ?? 'Rejected without reason.';

        $reject($grievance, $request->user(), $reason);

        return back()->with('success', 'Grievance rejected.');
    }
}
