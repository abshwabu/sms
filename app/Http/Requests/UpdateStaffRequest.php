<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();
        $staff = $this->route('staff');
        $userId = is_object($staff) ? $staff->user_id : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_title' => ['sometimes', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'on_leave', 'resigned', 'terminated'])],
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
