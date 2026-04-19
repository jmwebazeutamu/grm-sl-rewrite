<?php

declare(strict_types=1);

namespace App\Domain\Locality\Http\Controllers\Admin;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeographyController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (\Illuminate\Http\Request $request, \Closure $next) {
            abort_unless($request->user()?->hasRole('super-admin'), 403);

            return $next($request);
        });
    }

    public function index(): Response
    {
        $regions = Region::withCount('districts')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'children_count' => $r->districts_count]);

        return Inertia::render('Geography/Index', [
            'regions' => $regions,
        ]);
    }

    public function show(Region $region): Response
    {
        $districts = $region->districts()
            ->withCount('chiefdoms')
            ->orderBy('name')
            ->get(['id', 'name', 'region_id'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'children_count' => $d->chiefdoms_count]);

        return Inertia::render('Geography/Districts', [
            'region' => ['id' => $region->id, 'name' => $region->name],
            'districts' => $districts,
        ]);
    }

    public function showDistrict(District $district): Response
    {
        $district->loadMissing('region:id,name');
        $chiefdoms = $district->chiefdoms()
            ->withCount('sections')
            ->orderBy('name')
            ->get(['id', 'name', 'district_id'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'children_count' => $c->sections_count]);

        return Inertia::render('Geography/Chiefdoms', [
            'district' => ['id' => $district->id, 'name' => $district->name],
            'region' => ['id' => $district->region->id, 'name' => $district->region->name],
            'chiefdoms' => $chiefdoms,
        ]);
    }

    public function showChiefdom(Chiefdom $chiefdom): Response
    {
        $chiefdom->loadMissing('district.region');
        $sections = $chiefdom->sections()
            ->withCount('localities')
            ->orderBy('name')
            ->get(['id', 'name', 'chiefdom_id'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'children_count' => $s->localities_count]);

        return Inertia::render('Geography/Sections', [
            'chiefdom' => ['id' => $chiefdom->id, 'name' => $chiefdom->name],
            'district' => ['id' => $chiefdom->district->id, 'name' => $chiefdom->district->name],
            'region' => ['id' => $chiefdom->district->region->id, 'name' => $chiefdom->district->region->name],
            'sections' => $sections,
        ]);
    }

    public function showSection(Section $section): Response
    {
        $section->loadMissing('chiefdom.district.region');
        $localities = $section->localities()
            ->orderBy('name')
            ->get(['id', 'name', 'section_id'])
            ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]);

        return Inertia::render('Geography/Localities', [
            'section' => ['id' => $section->id, 'name' => $section->name],
            'chiefdom' => ['id' => $section->chiefdom->id, 'name' => $section->chiefdom->name],
            'district' => ['id' => $section->chiefdom->district->id, 'name' => $section->chiefdom->district->name],
            'region' => ['id' => $section->chiefdom->district->region->id, 'name' => $section->chiefdom->district->region->name],
            'localities' => $localities,
        ]);
    }

    public function store(string $level, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['required', 'integer'],
        ]);

        match ($level) {
            'regions' => Region::create(['name' => $data['name'], 'country_id' => $data['parent_id']]),
            'districts' => District::create(['name' => $data['name'], 'region_id' => $data['parent_id']]),
            'chiefdoms' => Chiefdom::create(['name' => $data['name'], 'district_id' => $data['parent_id']]),
            'sections' => Section::create(['name' => $data['name'], 'chiefdom_id' => $data['parent_id']]),
            'localities' => Locality::create(['name' => $data['name'], 'section_id' => $data['parent_id']]),
            default => abort(404, 'Unknown geography level'),
        };

        return back()->with('success', ucfirst(rtrim($level, 's')).' added.');
    }

    public function update(string $level, int $id, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $model = $this->resolveModel($level, $id);
        $model->update(['name' => $data['name']]);

        return back()->with('success', 'Updated.');
    }

    public function destroy(string $level, int $id): RedirectResponse
    {
        $model = $this->resolveModel($level, $id);
        $childRel = $this->childRelationName($level);

        if ($childRel !== null) {
            $count = $model->{$childRel}()->count();
            if ($count > 0) {
                $noun = rtrim($childRel, 's');

                return back()->with('error', "Cannot delete — {$count} child {$noun} record".($count === 1 ? '' : 's').' exist'.($count === 1 ? 's' : '').'. Remove them first.');
            }
        }

        $model->delete();

        return back()->with('success', 'Deleted.');
    }

    private function resolveModel(string $level, int $id)
    {
        return match ($level) {
            'regions' => Region::findOrFail($id),
            'districts' => District::findOrFail($id),
            'chiefdoms' => Chiefdom::findOrFail($id),
            'sections' => Section::findOrFail($id),
            'localities' => Locality::findOrFail($id),
            default => abort(404, 'Unknown geography level'),
        };
    }

    private function childRelationName(string $level): ?string
    {
        return match ($level) {
            'regions' => 'districts',
            'districts' => 'chiefdoms',
            'chiefdoms' => 'sections',
            'sections' => 'localities',
            'localities' => null,
            default => null,
        };
    }
}
