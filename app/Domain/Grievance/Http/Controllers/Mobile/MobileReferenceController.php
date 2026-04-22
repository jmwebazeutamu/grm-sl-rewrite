<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Mobile;

use App\Domain\Grievance\Models\OrgGrievanceType;
use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Reference (lookup) data for mobile. Cached for 1 hour since none of
 * these tables change often.
 */
class MobileReferenceController extends Controller
{
    private const TTL = 3600;

    public function regions(): JsonResponse
    {
        return $this->cached('regions', fn () => Region::orderBy('name')->get(['id', 'name']));
    }

    public function districts(Request $request): JsonResponse
    {
        $regionId = $request->integer('region_id') ?: null;
        $key = "districts:{$regionId}";

        return $this->cached($key, function () use ($regionId) {
            return District::when($regionId, fn ($q, $id) => $q->where('region_id', $id))
                ->orderBy('name')->get(['id', 'name', 'region_id']);
        });
    }

    public function chiefdoms(Request $request): JsonResponse
    {
        $districtId = $request->integer('district_id') ?: null;

        return $this->cached("chiefdoms:{$districtId}", function () use ($districtId) {
            return Chiefdom::when($districtId, fn ($q, $id) => $q->where('district_id', $id))
                ->orderBy('name')->get(['id', 'name', 'district_id']);
        });
    }

    public function sections(Request $request): JsonResponse
    {
        $chiefdomId = $request->integer('chiefdom_id') ?: null;

        return $this->cached("sections:{$chiefdomId}", function () use ($chiefdomId) {
            return Section::when($chiefdomId, fn ($q, $id) => $q->where('chiefdom_id', $id))
                ->orderBy('name')->get(['id', 'name', 'chiefdom_id']);
        });
    }

    public function localities(Request $request): JsonResponse
    {
        $sectionId = $request->integer('section_id') ?: null;

        return $this->cached("localities:{$sectionId}", function () use ($sectionId) {
            return Locality::when($sectionId, fn ($q, $id) => $q->where('section_id', $id))
                ->orderBy('name')->get(['id', 'name', 'section_id']);
        });
    }

    public function types(): JsonResponse
    {
        return $this->cached('types', fn () => GrievanceType::orderBy('name')->get(['id', 'name']));
    }

    public function howReported(): JsonResponse
    {
        return $this->cached('how_reported', fn () => HowReported::orderBy('name')->get(['id', 'name']));
    }

    public function organisations(): JsonResponse
    {
        return $this->cached('organisations', fn () => Organization::orderBy('name')->get(['id', 'name', 'acronym']));
    }

    public function programmes(Request $request): JsonResponse
    {
        $orgId = $request->integer('organisation_id') ?: null;

        return $this->cached("programmes:{$orgId}", function () use ($orgId) {
            return Programme::active()
                ->when($orgId, fn ($q, $id) => $q->where('organization_id', $id))
                ->orderBy('name')
                ->get(['id', 'name', 'acronym', 'organization_id']);
        });
    }

    /** AUTH — staff in a given organisation (candidates for assignment). */
    public function officers(Request $request): JsonResponse
    {
        $orgId = $request->integer('organisation_id') ?: null;

        return $this->cached("officers:{$orgId}", function () use ($orgId) {
            return User::when($orgId, fn ($q, $id) => $q->where('organization_id', $id))
                ->orderBy('name')
                ->get(['id', 'name']);
        });
    }

    /** AUTH — an org's internal sub-classifications (label renamed to `name` for the client SelectSheet). */
    public function orgClassifications(Request $request): JsonResponse
    {
        $orgId = $request->integer('organisation_id') ?: null;
        if ($orgId === null) {
            return response()->json(['data' => []]);
        }

        return $this->cached("org_classifications:{$orgId}", function () use ($orgId) {
            return OrgGrievanceType::where('organization_id', $orgId)
                ->where('active', true)
                ->orderBy('label')
                ->get(['id', 'label'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->label]);
        });
    }

    private function cached(string $key, \Closure $resolver): JsonResponse
    {
        $data = Cache::remember("mobile:ref:{$key}", self::TTL, $resolver);

        return response()->json(['data' => $data]);
    }
}
