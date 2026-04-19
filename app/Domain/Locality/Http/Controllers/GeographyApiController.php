<?php

declare(strict_types=1);

namespace App\Domain\Locality\Http\Controllers;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Section;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeographyApiController extends Controller
{
    public function districts(Request $request): JsonResponse
    {
        $regionId = (int) $request->query('region_id', 0);
        $rows = $regionId > 0
            ? District::where('region_id', $regionId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return response()->json($rows);
    }

    public function chiefdoms(Request $request): JsonResponse
    {
        $districtId = (int) $request->query('district_id', 0);
        $rows = $districtId > 0
            ? Chiefdom::where('district_id', $districtId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return response()->json($rows);
    }

    public function sections(Request $request): JsonResponse
    {
        $chiefdomId = (int) $request->query('chiefdom_id', 0);
        $rows = $chiefdomId > 0
            ? Section::where('chiefdom_id', $chiefdomId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return response()->json($rows);
    }

    public function localities(Request $request): JsonResponse
    {
        $sectionId = (int) $request->query('section_id', 0);
        $rows = $sectionId > 0
            ? Locality::where('section_id', $sectionId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return response()->json($rows);
    }
}
