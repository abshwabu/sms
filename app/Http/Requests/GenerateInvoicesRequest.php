<?php

namespace App\Http\Requests;

class GenerateInvoicesRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'due_date' => ['nullable', 'date'],
            'override_existing' => ['sometimes', 'boolean'],
        ];
    }
}
