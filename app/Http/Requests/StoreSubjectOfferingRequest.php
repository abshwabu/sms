<?php

namespace App\Http\Requests;

class StoreSubjectOfferingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'type' => ['sometimes', 'string', 'in:core,elective'],
            'max_students' => ['nullable', 'integer', 'min:1'],
            'enrollment_start' => ['nullable', 'date'],
            'enrollment_end' => ['nullable', 'date'],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }
}
