<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\PostAction;
use App\Domain\Grievance\Http\Requests\PostActionRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ActionController extends Controller
{
    public function store(
        PostActionRequest $request,
        Grievance $grievance,
        PostAction $post,
    ): RedirectResponse {
        $this->authorize('transition', $grievance);

        $post($grievance, $request->toDomain(), $request->user());

        return back()->with('success', 'Action posted.');
    }
}
