<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class StoreAcademicYearRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_years', 'name')->where('school_id', $schoolId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
