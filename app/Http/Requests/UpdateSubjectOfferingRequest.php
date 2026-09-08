<?php

namespace App\Http\Requests;

class UpdateSubjectOfferingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:core,elective'],
            'max_students' => ['nullable', 'integer', 'min:1'],
            'enrollment_start' => ['nullable', 'date'],
            'enrollment_end' => ['nullable', 'date'],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }
}
