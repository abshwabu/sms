<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class InviteParentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'student_id' => [
                'nullable',
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'relationship' => ['nullable', 'string', Rule::in(['mother', 'father', 'guardian', 'other'])],
            'is_primary_contact' => ['nullable', 'boolean'],
        ];
    }
}
