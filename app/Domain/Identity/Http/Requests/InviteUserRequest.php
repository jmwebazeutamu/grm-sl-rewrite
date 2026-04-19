<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Requests;

use App\Domain\Organization\Models\Office;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inviting a user into the acting user's own org. Role is limited here to
 * the two roles an org-admin may grant (org-admin can't self-replicate;
 * can't promote to acc-reviewer or super-admin).
 *
 * office_id is optional — an invited user may or may not be assigned to a
 * specific office at creation time. If provided, the office must belong to
 * the acting user's own org.
 */
class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy gate on route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $actorOrgId = $this->user()?->organization_id;

        return [
            'username' => ['required', 'string', 'max:50', 'unique:person,username'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:person,email'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:150'],
            'office_id' => [
                'nullable', 'integer',
                Rule::exists('office', 'id')->where(function ($query) use ($actorOrgId): void {
                    if ($actorOrgId !== null) {
                        $query->where('organization_id', $actorOrgId);
                    }
                }),
            ],
            'role' => ['required', 'string'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organization', 'id')],
        ];
    }
}
