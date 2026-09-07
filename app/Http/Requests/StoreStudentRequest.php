<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'admission_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'admission_number')->where('school_id', $schoolId),
            ],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:500'],
            'admission_date' => ['nullable', 'date'],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'medical_notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'string', 'max:2048'],
            'guardian_info' => ['nullable', 'array'],
        ];
    }
}
