<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Section;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'region_id' => ['nullable', 'integer', 'exists:region,id'],
            'district_id' => ['nullable', 'integer', 'exists:district,id'],
            'chiefdom_id' => ['nullable', 'integer', 'exists:chiefdom,id'],
            'section_id' => ['nullable', 'integer', 'exists:section,id'],
            'locality_id' => ['nullable', 'integer', 'exists:locality,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $region = $this->input('region_id');
            $district = $this->input('district_id');
            $chiefdom = $this->input('chiefdom_id');
            $section = $this->input('section_id');
            $locality = $this->input('locality_id');

            if ($district && $region && (int) District::whereKey($district)->value('region_id') !== (int) $region) {
                $v->errors()->add('district_id', 'District does not belong to the selected region.');
            }
            if ($chiefdom && $district && (int) Chiefdom::whereKey($chiefdom)->value('district_id') !== (int) $district) {
                $v->errors()->add('chiefdom_id', 'Chiefdom does not belong to the selected district.');
            }
            if ($section && $chiefdom && (int) Section::whereKey($section)->value('chiefdom_id') !== (int) $chiefdom) {
                $v->errors()->add('section_id', 'Section does not belong to the selected chiefdom.');
            }
            if ($locality && $section && (int) Locality::whereKey($locality)->value('section_id') !== (int) $section) {
                $v->errors()->add('locality_id', 'Locality does not belong to the selected section.');
            }
        });
    }
}
