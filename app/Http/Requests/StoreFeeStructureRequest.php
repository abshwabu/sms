<?php

namespace App\Http\Requests;

class StoreFeeStructureRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:tuition,transport,registration,activity,facility,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'condition_type' => ['sometimes', 'string', 'in:none,transport_enrollment,custom'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
