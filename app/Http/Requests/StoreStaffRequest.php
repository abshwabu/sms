<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends BaseApiRequest
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
            'staff_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('staff', 'staff_number')->where('school_id', $schoolId),
            ],
            'role_title' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['active', 'on_leave', 'resigned', 'terminated'])],
            'qualification' => ['nullable', 'string', 'max:1000'],
            'subjects_taught' => ['nullable', 'array'],
            'subjects_taught.*' => ['string'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => [
                'integer',
                Rule::exists('courses', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
