<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Base controller for the 10 near-identical reference/lookup tables.
 *
 * Concrete subclasses declare the model class, the route/view key, and the
 * validation rules. Everything else is shared.
 */
abstract class ReferenceController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** The route-binding key (e.g. "grievance-types") and Vue page folder. */
    abstract protected function key(): string;

    /**
     * Validation rules for store/update. $id is null on store.
     *
     * @return array<string, array<int, string|ValidationRule|\Illuminate\Validation\Rules\Unique>>
     */
    abstract protected function rules(?int $id = null): array;

    public function index(Request $request): Response
    {
        $items = QueryBuilder::for($this->model())
            ->allowedFilters([AllowedFilter::partial('name')])
            ->allowedSorts(['name'])
            ->defaultSort('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render("Reference/{$this->folder()}/Index", [
            'items' => $items,
            'filters' => $request->only('filter'),
            'resource' => $this->key(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render("Reference/{$this->folder()}/Create", [
            'resource' => $this->key(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $model = $this->model()::create($data);

        return redirect()
            ->route("admin.reference.{$this->key()}.show", $model)
            ->with('success', ucfirst(str_replace('-', ' ', $this->key())).' created.');
    }

    public function show(Model $model): Response
    {
        return Inertia::render("Reference/{$this->folder()}/Show", [
            'item' => $model,
            'resource' => $this->key(),
            ...$this->extraShowProps($model),
        ]);
    }

    /**
     * Hook for subclasses to inject additional props into the Show view.
     *
     * @return array<string, mixed>
     */
    protected function extraShowProps(Model $model): array
    {
        return [];
    }

    public function edit(Model $model): Response
    {
        return Inertia::render("Reference/{$this->folder()}/Edit", [
            'item' => $model,
            'resource' => $this->key(),
        ]);
    }

    public function update(Request $request, Model $model): RedirectResponse
    {
        $data = $request->validate($this->rules($model->id));

        $model->update($data);

        return redirect()
            ->route("admin.reference.{$this->key()}.show", $model)
            ->with('success', ucfirst(str_replace('-', ' ', $this->key())).' updated.');
    }

    public function destroy(Model $model): RedirectResponse
    {
        try {
            $model->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'FOREIGN KEY constraint failed')
                || str_contains($e->getMessage(), 'foreign key constraint')) {
                return redirect()
                    ->route("admin.reference.{$this->key()}.index")
                    ->with('error', 'Cannot delete — this item is still referenced by grievances or other records.');
            }
            throw $e;
        }

        return redirect()
            ->route("admin.reference.{$this->key()}.index")
            ->with('success', 'Deleted.');
    }

    protected function folder(): string
    {
        // grievance-types → GrievanceType
        return str_replace(' ', '', ucwords(str_replace('-', ' ', rtrim($this->key(), 's'))));
    }

    protected function uniqueName(?int $id = null, ?string $scope = null, ?int $scopeValue = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique($this->tableName(), 'name');

        if ($scope !== null && $scopeValue !== null) {
            $rule->where($scope, $scopeValue);
        }

        if ($id !== null) {
            $rule->ignore($id);
        }

        return $rule;
    }

    protected function tableName(): string
    {
        /** @var Model $instance */
        $instance = new ($this->model());

        return $instance->getTable();
    }
}
