<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GrievanceTypeController extends ReferenceController
{
    protected function model(): string
    {
        return GrievanceType::class;
    }

    protected function key(): string
    {
        return 'grievance-types';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueName($id)],
        ];
    }

    protected function extraShowProps(Model $model): array
    {
        return [
            'usage' => [
                'grievances' => DB::table('grievance')->where('grievance_type_id', $model->id)->count(),
                'caseConcepts' => DB::table('case_concept')->where('grievance_type_id', $model->id)->count(),
            ],
            'otherTypes' => GrievanceType::where('id', '!=', $model->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function reassignAndDestroy(Request $request, GrievanceType $grievanceType): RedirectResponse
    {
        $this->authorize('delete', $grievanceType);

        $data = $request->validate([
            'target_id' => [
                'required',
                'integer',
                Rule::notIn([$grievanceType->id]),
                Rule::exists('grievance_type', 'id'),
            ],
        ]);

        $grievancesMoved = 0;
        $conceptsMoved = 0;
        DB::transaction(function () use ($grievanceType, $data, &$grievancesMoved, &$conceptsMoved): void {
            $grievancesMoved = DB::table('grievance')
                ->where('grievance_type_id', $grievanceType->id)
                ->update(['grievance_type_id' => $data['target_id']]);
            $conceptsMoved = DB::table('case_concept')
                ->where('grievance_type_id', $grievanceType->id)
                ->update(['grievance_type_id' => $data['target_id']]);
            $grievanceType->delete();
        });

        $parts = [];
        if ($grievancesMoved > 0) {
            $parts[] = "{$grievancesMoved} grievance".($grievancesMoved === 1 ? '' : 's');
        }
        if ($conceptsMoved > 0) {
            $parts[] = "{$conceptsMoved} case concept".($conceptsMoved === 1 ? '' : 's');
        }
        $moved = $parts === [] ? 'no references' : implode(' and ', $parts);

        return redirect()
            ->route('admin.reference.grievance-types.index')
            ->with('success', "Reassigned {$moved}, then deleted \"{$grievanceType->name}\".");
    }
}
