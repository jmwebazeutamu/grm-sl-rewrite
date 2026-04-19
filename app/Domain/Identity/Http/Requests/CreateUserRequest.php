<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Direct-create user. Admin sets username + password themselves; no email
 * is sent. Use when the invitee has no email address or needs immediate
 * access.
 *
 * Email is optional here (nullable) — some staff in SL field offices don't
 * have email addresses. Username is the login identity.
 *
 * Password rules: Laravel default Password::default() — min 8 chars.
 * Must be confirmed (`password_confirmation` field). Hashed by the action
 * via Hash::make.
 */
class CreateUserRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:150', 'unique:person,email'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)],
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
