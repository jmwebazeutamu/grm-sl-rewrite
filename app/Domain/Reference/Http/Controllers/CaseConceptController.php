<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\CaseConcept;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CaseConceptController extends ReferenceController
{
    protected function model(): string
    {
        return CaseConcept::class;
    }

    protected function key(): string
    {
        return 'case-concepts';
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                $this->uniqueName($id, 'grievance_type_id', request()->integer('grievance_type_id')),
            ],
            'description' => ['nullable', 'string'],
            'grievance_type_id' => ['required', 'integer', 'exists:grievance_type,id'],
        ];
    }

    public function index(Request $request): Response
    {
        $items = QueryBuilder::for(CaseConcept::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('grievance_type_id'),
            ])
            ->allowedSorts(['name'])
            ->defaultSort('name')
            ->with('grievanceType:id,name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Reference/CaseConcept/Index', [
            'items' => $items,
            'filters' => $request->only('filter'),
            'resource' => $this->key(),
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Reference/CaseConcept/Create', [
            'resource' => $this->key(),
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Model $model): Response
    {
        return Inertia::render('Reference/CaseConcept/Edit', [
            'item' => $model,
            'resource' => $this->key(),
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
