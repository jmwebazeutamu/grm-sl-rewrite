<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\BeginClosureReview;
use App\Domain\Grievance\Actions\CloseGrievance;
use App\Domain\Grievance\Actions\EscalateAndReopen;
use App\Domain\Grievance\Http\Requests\ClosureActionRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClosureController extends Controller
{
    public function beginReview(Request $request, Grievance $grievance, BeginClosureReview $action): RedirectResponse
    {
        $this->authorize('adminReview', $grievance);

        try {
            $action($grievance, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Closure review started.');
    }

    public function close(ClosureActionRequest $request, Grievance $grievance, CloseGrievance $action): RedirectResponse
    {
        $this->authorize('closureAction', $grievance);

        if ($request->validated()['outcome'] !== 'satisfied') {
            return back()->with('error', 'Closing requires a satisfied outcome.');
        }

        try {
            $action($grievance, $request->validated()['closure_comment'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Grievance closed.');
    }

    public function escalate(ClosureActionRequest $request, Grievance $grievance, EscalateAndReopen $action): RedirectResponse
    {
        $this->authorize('closureAction', $grievance);

        if ($request->validated()['outcome'] !== 'dissatisfied') {
            return back()->with('error', 'Escalation requires a dissatisfied outcome.');
        }

        try {
            $action($grievance, $request->validated()['closure_comment'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Grievance reopened.');
    }
}
