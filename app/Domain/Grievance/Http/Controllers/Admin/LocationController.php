<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Http\Requests\UpdateLocationRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LocationController extends Controller
{
    public function update(Grievance $grievance, UpdateLocationRequest $request): RedirectResponse
    {
        $this->authorize('update', $grievance);

        $grievance->update($request->validated());

        return back()->with('success', 'Location updated.');
    }
}
