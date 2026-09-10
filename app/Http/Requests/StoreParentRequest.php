<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class StoreParentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
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
