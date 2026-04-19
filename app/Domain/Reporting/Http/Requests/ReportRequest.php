<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Http\Requests;

use App\Domain\Reporting\Services\ReportQueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', Rule::in(ReportQueryBuilder::VALID_FIELDS)],
            'filters' => ['nullable', 'array'],
            'filters.*.field' => ['required', 'string'],
            'filters.*.operator' => ['required', 'string', Rule::in(['=', '>', '<', '>=', '<=', 'between', 'in', 'contains'])],
            'filters.*.value' => ['required'],
        ];
    }
}
