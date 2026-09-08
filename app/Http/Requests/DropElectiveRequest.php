<?php

namespace App\Http\Requests;

class DropElectiveRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
        ];
    }
}
