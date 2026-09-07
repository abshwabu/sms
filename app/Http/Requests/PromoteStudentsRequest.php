<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class PromoteStudentsRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'target_academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'target_section_id' => [
                'required',
                'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
