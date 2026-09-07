<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();
        $student = $this->route('student');
        $studentId = is_object($student) ? $student->id : $student;
        $userId = is_object($student) ? $student->user_id : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'graduated', 'transferred', 'withdrawn'])],
            'medical_notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'string', 'max:2048'],
            'guardian_info' => ['nullable', 'array'],
        ];
    }
}
