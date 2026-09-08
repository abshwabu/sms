<?php

namespace App\Http\Requests;

class StoreGradeRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'grades.*.marks_obtained' => ['required', 'numeric', 'min:0'],
            'grades.*.max_marks' => ['nullable', 'numeric', 'min:1'],
            'grades.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
