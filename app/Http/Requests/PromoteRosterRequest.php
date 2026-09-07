<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class PromoteRosterRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'source_section_id' => [
                'required',
                'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'target_academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'target_section_id' => [
                Rule::requiredIf(fn () => in_array($this->input('action'), ['promote', 'retain'])),
                'nullable',
                'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'action' => ['required', 'string', Rule::in(['promote', 'retain', 'graduate'])],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
