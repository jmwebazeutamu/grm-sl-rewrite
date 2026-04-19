<?php

declare(strict_types=1);

namespace App\Domain\Locality\Http\Controllers;

use App\Domain\Locality\Actions\CreateLocality;
use App\Domain\Locality\Actions\UpdateLocality;
use App\Domain\Locality\Http\Requests\StoreLocalityRequest;
use App\Domain\Locality\Http\Requests\UpdateLocalityRequest;
use App\Domain\Locality\Http\Resources\LocalityResource;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Section;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LocalityController extends Controller
{
    public function index(Request $request): Response
    {
        $localities = QueryBuilder::for(Locality::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('section_id'),
            ])
            ->allowedSorts(['name'])
            ->defaultSort('name')
            ->with('section')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Locality/Index', [
            'localities' => LocalityResource::collection($localities),
            'filters' => $request->only('filter'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Locality/Create', [
            'sections' => Section::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreLocalityRequest $request, CreateLocality $create): RedirectResponse
    {
        $locality = $create($request->validated());

        return redirect()
            ->route('admin.localities.show', $locality)
            ->with('success', 'Locality created.');
    }

    public function show(Locality $locality): Response
    {
        return Inertia::render('Locality/Show', [
            'locality' => LocalityResource::make($locality->load('section')),
        ]);
    }

    public function edit(Locality $locality): Response
    {
        return Inertia::render('Locality/Edit', [
            'locality' => LocalityResource::make($locality),
            'sections' => Section::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateLocalityRequest $request, Locality $locality, UpdateLocality $update): RedirectResponse
    {
        $update($locality, $request->validated());

        return redirect()
            ->route('admin.localities.show', $locality)
            ->with('success', 'Locality updated.');
    }

    public function destroy(Locality $locality): RedirectResponse
    {
        $locality->delete();

        return redirect()
            ->route('admin.localities.index')
            ->with('success', 'Locality deleted.');
    }
}
