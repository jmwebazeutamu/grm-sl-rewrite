<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Enums\ProgrammeStatus;
use App\Domain\Organization\Models\Programme;
use App\Rules\Recaptcha;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SubmitGrievanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint; reCAPTCHA is the gate
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'grievance_type_id' => ['required', 'integer', 'exists:grievance_type,id'],
            'how_reported_id' => ['nullable', 'integer', 'exists:how_reported,id'],
            'priority_id' => ['nullable', 'integer', 'exists:priority,id'],
            'is_anonymous' => ['boolean'],

            'implementing_organization_id' => ['nullable', 'integer', 'exists:organization,id'],
            'programme_id' => ['nullable', 'integer', 'exists:programme,id'],

            'region_id' => ['nullable', 'integer', 'exists:region,id'],
            'district_id' => ['nullable', 'integer', 'exists:district,id'],
            'chiefdom_id' => ['nullable', 'integer', 'exists:chiefdom,id'],
            'section_id' => ['nullable', 'integer', 'exists:section,id'],
            'locality_id' => ['nullable', 'integer', 'exists:locality,id'],

            // Complainer is required unless the submission is anonymous.
            'complainer' => ['required_unless:is_anonymous,true', 'array'],
            'complainer.first_name' => ['required_with:complainer', 'string', 'max:100'],
            'complainer.last_name' => ['required_with:complainer', 'string', 'max:100'],
            'complainer.gender' => ['nullable', 'string', 'max:20'],
            'complainer.email' => ['nullable', 'email', 'max:150'],
            'complainer.phone_number' => ['nullable', 'string', 'max:30'],
            'complainer.address' => ['nullable', 'string', 'max:500'],
            'complainer.organization_id' => ['nullable', 'integer', 'exists:organization,id'],
            'complainer.other_organization' => ['nullable', 'string', 'max:200'],

            'suspects' => ['array', 'max:20'],
            'suspects.*.first_name' => ['nullable', 'string', 'max:100'],
            'suspects.*.last_name' => ['nullable', 'string', 'max:100'],
            'suspects.*.title' => ['nullable', 'string', 'max:100'],
            'suspects.*.gender' => ['nullable', 'string', 'max:20'],
            'suspects.*.phone_number' => ['nullable', 'string', 'max:30'],
            'suspects.*.email' => ['nullable', 'email', 'max:150'],
            'suspects.*.address' => ['nullable', 'string', 'max:500'],
            'suspects.*.organization_id' => ['nullable', 'integer', 'exists:organization,id'],
            'suspects.*.is_beneficiary' => ['nullable', 'boolean'],
            'suspects.*.programme_id' => ['nullable', 'integer', 'exists:programme,id'],
            'suspects.*.implementing_organization_id' => [
                'nullable', 'integer', 'exists:organization,id',
                'required_if:suspects.*.is_beneficiary,true,1',
            ],
            'suspects.*.beneficiary_id_number' => ['nullable', 'string', 'max:100'],

            'beneficiaries' => ['array', 'max:20'],
            'beneficiaries.*.name' => ['required_with:beneficiaries.*', 'string', 'max:200'],
            'beneficiaries.*.gender' => ['nullable', 'string', 'max:20'],
            'beneficiaries.*.phone_number' => ['nullable', 'string', 'max:30'],
            'beneficiaries.*.household_id' => ['nullable', 'string', 'max:50'],
            'beneficiaries.*.implementing_agency_id' => ['nullable', 'integer', 'exists:organization,id'],
            'beneficiaries.*.social_programme_id' => ['nullable', 'integer', 'exists:programme,id'],

            'attachments' => ['array', 'max:10'],
            'attachments.*' => [
                'file', 'max:10240',
                'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,mp3,mp4,m4a,txt',
            ],

            'recaptcha_token' => ['bail', 'required', 'string', new Recaptcha(0.5)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateLocationHierarchy($v);

            $programmeId = $this->input('programme_id');
            if ($programmeId === null) {
                return;
            }

            $programme = Programme::find($programmeId);
            if ($programme === null) {
                return;
            }

            if ($programme->status !== ProgrammeStatus::Active) {
                $v->errors()->add('programme_id', 'The selected programme is no longer active.');

                return;
            }

            $orgId = $this->input('implementing_organization_id');
            if ($orgId !== null && (int) $programme->organization_id !== (int) $orgId) {
                $v->errors()->add('programme_id', 'The selected programme does not belong to the chosen organisation.');
            }
        });
    }

    private function validateLocationHierarchy(Validator $v): void
    {
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
    }
}
