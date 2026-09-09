<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreTermRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $academicYearId = $this->route('academicYear')?->id ?? $this->input('academic_year_id');

        return [
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('terms', 'name')->where('academic_year_id', $academicYearId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
