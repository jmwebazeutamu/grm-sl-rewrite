<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\CategorizeGrievance;
use App\Domain\Grievance\Http\Requests\CategorizeRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;

class CategorizationController extends Controller
{
    public function update(
        CategorizeRequest $request,
        Grievance $grievance,
        CategorizeGrievance $categorize,
    ): RedirectResponse {
        $this->authorize('classify', $grievance);

        try {
            $categorize($grievance, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Grievance categorized and assigned to organization.');
    }
}
